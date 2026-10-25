<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\post;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    Storage::fake('public');

    $user = User::where('role', 'super admin')->first();
    $this->withHeaders(['company' => $user->companies()->first()->id]);
    Sanctum::actingAs($user, ['*']);
});

/**
 * A JPEG of the given size carrying an EXIF block with an orientation tag and
 * a GPS section, like a phone photo.
 */
function jpegWithLocation(int $width, int $height, int $orientation = 1): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagejpeg($image);
    $jpeg = ob_get_clean();

    // TIFF header (little-endian), IFD0 at offset 8 with Orientation and the GPS IFD pointer.
    $ifd0 = pack('v', 2)
        .pack('vvVvv', 0x0112, 3, 1, $orientation, 0)
        .pack('vvVV', 0x8825, 4, 1, 8 + 30)
        .pack('V', 0);
    // GPS IFD: GPSLatitudeRef = "N".
    $gps = pack('v', 1)
        .pack('vvV', 0x0001, 2, 2).'N'."\0\0\0"
        .pack('V', 0);
    $exif = "Exif\0\0".'II'.pack('vV', 42, 8).$ifd0.$gps;
    $app1 = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;

    $path = tempnam(sys_get_temp_dir(), 'exif').'.jpg';
    file_put_contents($path, substr($jpeg, 0, 2).$app1.substr($jpeg, 2));

    return $path;
}

function storedAvatarPath(): string
{
    $media = User::where('role', 'super admin')->first()->getFirstMedia('admin_avatar');

    return Storage::disk('public')->path($media->getPathRelativeToRoot());
}

test('the fixture photo really carries a location', function () {
    expect(exif_read_data(jpegWithLocation(4, 2), null, true))->toHaveKey('GPS');
});

test('an uploaded photo is stored without its location', function () {
    $photo = new UploadedFile(jpegWithLocation(4, 2), 'selfie.jpg', 'image/jpeg', null, true);

    post('/api/v1/me/upload-avatar', ['admin_avatar' => $photo])->assertOk();

    expect(exif_read_data(storedAvatarPath(), null, true) ?: [])->not->toHaveKey('GPS');
});

test('a photo taken sideways keeps its orientation and nothing else', function () {
    // Orientation 6: the camera stored the pixels rotated; viewers turn it 90° clockwise.
    $photo = new UploadedFile(jpegWithLocation(4, 2, 6), 'selfie.jpg', 'image/jpeg', null, true);

    post('/api/v1/me/upload-avatar', ['admin_avatar' => $photo])->assertOk();

    $exif = exif_read_data(storedAvatarPath(), null, true);

    expect($exif['IFD0']['Orientation'])->toBe(6)
        ->and($exif)->not->toHaveKey('GPS')
        ->and(getimagesize(storedAvatarPath())[0])->toBe(4);
});

test('a photo with no metadata is stored unchanged', function () {
    $image = imagecreatetruecolor(3, 3);
    $path = tempnam(sys_get_temp_dir(), 'jpg').'.jpg';
    imagejpeg($image, $path);
    $original = file_get_contents($path);

    $photo = new UploadedFile($path, 'plain.jpg', 'image/jpeg', null, true);

    post('/api/v1/me/upload-avatar', ['admin_avatar' => $photo])->assertOk();

    expect(file_get_contents(storedAvatarPath()))->toBe($original);
});

test('a png is stored byte for byte', function () {
    $image = imagecreatetruecolor(3, 3);
    $path = tempnam(sys_get_temp_dir(), 'png').'.png';
    imagepng($image, $path);
    $original = file_get_contents($path);

    $logo = new UploadedFile($path, 'logo.png', 'image/png', null, true);

    post('/api/v1/me/upload-avatar', ['admin_avatar' => $logo])->assertOk();

    expect(file_get_contents(storedAvatarPath()))->toBe($original);
});
