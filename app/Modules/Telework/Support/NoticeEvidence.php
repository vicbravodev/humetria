<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Models\WorkerNotice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Keeps the evidence that closes an accident notice on the private disk (rediseño v2, board V3-Aviso): a PDF as it came,
 * a photo re-encoded by `PhotoStore`, so nothing the camera wrote in it (GPS included) survives.
 */
final readonly class NoticeEvidence
{
    public function __construct(private FileStore $files, private PhotoStore $photos) {}

    /**
     * @return string the path of the stored evidence.
     *
     * @throws ValidationException when the photo is no JPG or PNG image.
     */
    public function store(WorkerNotice $notice, UploadedFile $evidence): string
    {
        $directory = "notices/{$notice->tenant_id}/{$notice->id}";

        if ($evidence->getMimeType() !== 'application/pdf') {
            try {
                return $this->photos->store($evidence, $directory);
            } catch (ValidationException $refused) {
                throw ValidationException::withMessages(['evidence' => $refused->validator->errors()->first()]);
            }
        }

        $contents = file_get_contents($evidence->getPathname());

        // Laravel turns the warning of a failed read into an ErrorException first: the check only narrows the type.
        if ($contents === false) { // @pest-mutate-ignore: FalseToTrue
            throw new RuntimeException('The evidence could not be read.');
        }

        $path = "{$directory}/evidence-".Str::uuid().'.pdf';
        $this->files->put($path, $contents);

        return $path;
    }
}
