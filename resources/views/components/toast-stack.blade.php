{{--
    Toast notifications (one stack per page, included by the layout).
    From PHP:  return back()->with('toast', ['type' => 'success', 'message' => 'Saved']);
    From JS:   $store.toasts.push('success', 'Saved')
    Types: success | error | warning | info
--}}
<div class="pointer-events-none fixed inset-x-4 bottom-4 z-50 flex flex-col items-end gap-2 sm:left-auto sm:w-96"
     role="status" aria-live="polite"
     x-data
     @if (session('toast'))
         x-init="$nextTick(() => $store.toasts.push(@js(session('toast.type', 'info')), @js(session('toast.message', ''))))"
     @endif>
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div class="pointer-events-auto flex w-full items-start gap-3 rounded-card border bg-white p-4 shadow-overlay"
             :class="{
                 'border-success/40': toast.type === 'success',
                 'border-danger/40': toast.type === 'error',
                 'border-warning/40': toast.type === 'warning',
                 'border-primary-600/40': toast.type === 'info',
             }"
             x-transition.opacity>
            <span class="mt-0.5"
                  :class="{
                      'text-success': toast.type === 'success',
                      'text-danger': toast.type === 'error',
                      'text-warning': toast.type === 'warning',
                      'text-info': toast.type === 'info',
                  }">
                <template x-if="toast.type === 'success'"><x-icon name="check-circle" /></template>
                <template x-if="toast.type === 'error'"><x-icon name="x-circle" /></template>
                <template x-if="toast.type === 'warning'"><x-icon name="alert" /></template>
                <template x-if="toast.type === 'info'"><x-icon name="info" /></template>
            </span>
            <p class="flex-1 text-base text-neutral-900" x-text="toast.message"></p>
            <button type="button" class="-m-2 inline-flex h-9 w-9 items-center justify-center rounded-control text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900"
                    @click="$store.toasts.dismiss(toast.id)">
                <x-icon name="x" class="h-4 w-4" /><span class="sr-only">Dismiss</span>
            </button>
        </div>
    </template>
</div>
