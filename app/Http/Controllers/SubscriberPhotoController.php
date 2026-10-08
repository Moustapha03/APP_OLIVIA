<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use App\Services\Photos\SubscriberPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a subscriber photo from the private disk, never cached by shared
 * proxies. Allowed with either:
 * - a short-lived signed link, made for the pass a controller just scanned
 *   (VerifyPass::photoUrl): controllers cannot fetch photos by id;
 * - SubscriberPolicy::viewPhoto (owner account, desk / agency staff).
 */
class SubscriberPhotoController extends Controller
{
    public function __invoke(Request $request, Subscriber $subscriber): StreamedResponse
    {
        if (! $request->hasValidRelativeSignature()) {
            Gate::authorize('viewPhoto', $subscriber);
        }

        abort_if($subscriber->photo_path === null || ! Storage::disk(SubscriberPhoto::DISK)->exists($subscriber->photo_path), 404);

        return Storage::disk(SubscriberPhoto::DISK)->response($subscriber->photo_path, 'photo.jpg', [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
