<?php

declare(strict_types=1);

namespace Tests\Feature\Platform\Attachments;

use App\Models\User;
use App\Modules\Security\Domain\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait InteractsWithAttachments
{
    protected function setUpAttachments(string $role = Roles::AGENT): User
    {
        $this->setUpSpaOrigin();
        $this->seed(RolesAndPermissionsSeeder::class);

        Storage::fake('attachments');

        $user = User::factory()->create();
        $user->syncRoles([$role]);
        $this->actingAs($user->refresh());

        return $user;
    }

    /** A real PNG, so finfo sniffs it as one rather than as octet-stream. */
    protected function pngFile(string $name = 'receipt.png'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'png');
        $image = imagecreatetruecolor(4, 4);
        imagepng($image, $path);
        imagedestroy($image);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    /** A file whose contents are HTML whatever the client calls it. */
    protected function htmlFile(string $name = 'invoice.png', string $claimedMime = 'image/png'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'html');
        file_put_contents($path, "<!DOCTYPE html><html><body><script>alert(1)</script></body></html>");

        return new UploadedFile($path, $name, $claimedMime, null, true);
    }

    protected function ownerId(): string
    {
        return (string) Str::ulid();
    }
}
