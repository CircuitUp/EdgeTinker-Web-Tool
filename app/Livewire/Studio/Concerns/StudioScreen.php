<?php

namespace App\Livewire\Studio\Concerns;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Small helpers shared by Content Studio screens. */
trait StudioScreen
{
    protected function user(): User
    {
        return auth()->user();
    }

    protected function toast(string $type, string $message): void
    {
        $this->dispatch('toast', type: $type, message: $message);
    }

    /** Show a toast on the next page after a redirect. */
    protected function flashToast(string $type, string $message): void
    {
        session()->flash('toast', ['type' => $type, 'message' => $message]);
    }

    /**
     * Runs an action from the workflow service and, if it refuses, shows the
     * reason as a toast instead of an error page. Returns false when refused.
     */
    protected function attempt(callable $action): bool
    {
        try {
            $action();

            return true;
        } catch (ValidationException $e) {
            $this->toast('error', collect($e->errors())->flatten()->first() ?? 'That could not be done.');

            return false;
        }
    }

    /** Re-key service validation errors ("name") to this form's fields ("form.name"). */
    protected function showFormErrors(ValidationException $e, string $prefix = 'form.'): void
    {
        $bag = $this->getErrorBag();
        foreach ($e->errors() as $key => $messages) {
            foreach ($messages as $message) {
                $bag->add($prefix.$key, $message);
            }
        }

        $this->toast('error', 'Please check the highlighted fields: '.collect($e->errors())->flatten()->first());
    }
}
