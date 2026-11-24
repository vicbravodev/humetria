<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Requests;

use App\Modules\Notifications\Support\PushHosts;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * What `PushSubscription.toJSON()` gives (any other key, like `contentEncoding`, is ignored). The session of the person is the
 * credential; the address must be a push service of a browser (PushHosts) and the keys the size a browser gives (65 and 16 bytes).
 */
final class StorePushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'max:2048', 'url:https', $this->pushHost(...)],
            'keys.p256dh' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{87}$/'],
            'keys.auth' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{22}$/'],
        ];
    }

    /** @param  Closure(string): PotentiallyTranslatedString  $fail */
    private function pushHost(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! PushHosts::allows($value)) {
            $fail('validation.url')->translate();
        }
    }
}
