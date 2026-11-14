<?php

namespace App\Studio;

use App\Jobs\GenerateImageVariants;
use App\Models\BannerSlide;
use App\Models\Category;
use App\Models\Media;
use App\Models\ProductDocument;
use App\Models\ProductImage;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Uploads to the media library.
 *
 * Only an explicit allow-list of file types is accepted, checked by both the
 * file extension and the detected content type. SVG and HTML are not accepted
 * (they can carry scripts), and nothing executable can be stored: files are
 * saved under a random name with an extension from the allow-list.
 */
class MediaLibrary
{
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public const DOCUMENT_EXTENSIONS = ['pdf', 'zip', 'step', 'stp', 'stl', 'bin', 'hex', 'uf2', 'txt', 'csv'];

    public const MAX_IMAGE_KB = 8 * 1024;       // 8 MB

    public const MAX_DOCUMENT_KB = 30 * 1024;   // 30 MB

    /** Validation rules for one uploaded file of the given kind. */
    public static function rules(string $kind): array
    {
        return $kind === Media::KIND_IMAGE
            ? ['required', 'file', 'max:'.self::MAX_IMAGE_KB, 'mimes:'.implode(',', self::IMAGE_EXTENSIONS), 'dimensions:max_width=8000,max_height=8000']
            : ['required', 'file', 'max:'.self::MAX_DOCUMENT_KB, 'extensions:'.implode(',', self::DOCUMENT_EXTENSIONS),
                // Detected content types for the allowed document formats (firmware and CAD files are opaque binaries).
                'mimetypes:application/pdf,application/zip,application/x-zip-compressed,application/octet-stream,text/plain,text/csv,model/stl,application/sla,application/vnd.ms-pki.stl,model/step,application/step'];
    }

    public function store(UploadedFile $file, string $kind, ?User $user): Media
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $allowed = $kind === Media::KIND_IMAGE ? self::IMAGE_EXTENSIONS : self::DOCUMENT_EXTENSIONS;

        if (! in_array($extension, $allowed, true)) {
            throw ValidationException::withMessages(['file' => 'This type of file is not allowed.']);
        }

        // A file can look like an image to the browser yet be damaged. Make sure
        // the server can actually read it, otherwise resizing would fail later.
        if ($kind === Media::KIND_IMAGE) {
            try {
                GenerateImageVariants::imageManager()->decodePath($file->getRealPath());
            } catch (\Throwable) {
                throw ValidationException::withMessages(['file' => $file->getClientOriginalName().' could not be read as an image. '
                    .'The file may be damaged; try exporting it again.']);
            }
        }

        $path = $file->storeAs('media/'.now()->format('Y/m'), Str::uuid().'.'.$extension, 'public');
        [$width, $height] = $kind === Media::KIND_IMAGE ? (@getimagesize($file->getRealPath()) ?: [null, null]) : [null, null];

        $media = Media::create([
            'kind' => $kind,
            'path' => $path,
            'original_name' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'alt' => '',
            'uploaded_by' => $user?->id,
        ]);

        if ($media->isImage()) {
            GenerateImageVariants::dispatch($media->id); // resizing runs on the queue
        }

        Audit::log('media.uploaded', "Uploaded {$media->original_name}", $media, actor: $user);

        return $media;
    }

    /** Where a file is used: live products and pending changes. Used files cannot be deleted. */
    public function usage(Media $media): array
    {
        $uses = [];

        foreach (ProductImage::with('product:id,name')->where('media_id', $media->id)->get() as $image) {
            $uses[] = 'Product image: '.($image->product->name ?? 'unknown product');
        }
        foreach (ProductDocument::with('product:id,name')->where('media_id', $media->id)->get() as $document) {
            $uses[] = 'Product document: '.($document->product->name ?? 'unknown product');
        }
        if (Category::where('image_path', $media->path)->exists()) {
            $uses[] = 'Category image';
        }
        if (BannerSlide::where('image_path', $media->path)->exists()) {
            $uses[] = 'Home page slide';
        }
        if (setting('site.logo_path') === $media->path) {
            $uses[] = 'Site logo';
        }

        // Pending changes store media ids inside their JSON payload.
        $pending = Revision::query()->pending()->whereNotNull('payload')
            ->where('payload', 'like', '%"media_id":'.$media->id.'%')->get(['title', 'payload']);
        foreach ($pending as $revision) {
            if (preg_match('/"media_id":'.$media->id.'(?!\d)/', json_encode($revision->payload))) {
                $uses[] = "Pending change: {$revision->title}";
            }
        }

        return array_values(array_unique($uses));
    }

    public function delete(Media $media, User $user): void
    {
        if ($uses = $this->usage($media)) {
            throw ValidationException::withMessages(['media' => 'This file is in use and cannot be deleted: '.implode('; ', $uses).'.']);
        }

        Storage::disk('public')->delete(array_merge([$media->path], array_values($media->variants ?? [])));
        Audit::log('media.deleted', "Deleted {$media->original_name}", $media, actor: $user);
        $media->delete();
    }
}
