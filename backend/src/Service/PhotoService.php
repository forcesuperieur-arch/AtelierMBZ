<?php

namespace App\Service;

use App\Entity\PhotoIntervention;
use App\Entity\RendezVous;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class PhotoService
{
    private const ALLOWED_TYPES = ['reception', 'avant_travaux', 'en_cours', 'apres_travaux', 'restitution', 'probleme'];
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_SIZE = 10 * 1024 * 1024; // 10MB

    private const MAGIC_BYTES = [
        'image/jpeg' => ['\xFF\xD8\xFF'],
        'image/png'  => ['\x89\x50\x4E\x47'],
        'image/webp' => ['\x52\x49\x46\x46'],
    ];

    private const TRANSITION_PHOTO_REQUIREMENTS = [
        // Au moins 1 photo de réception obligatoire (uploadée via Companion client).
        // Le Companion assigne automatiquement type='reception' depuis la v2.
        'reception' => ['type' => 'reception', 'min' => 1],
        'terminer' => ['type' => 'apres_travaux', 'min' => 2],
        'restituer' => ['type' => 'restitution', 'min' => 3],
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private SluggerInterface $slugger,
        private string $projectDir,
    ) {}

    public function allowedTypes(): array
    {
        return self::ALLOWED_TYPES;
    }

    public function upload(UploadedFile $file, string $type, RendezVous $rdv, ?string $description = null, ?string $annotationJson = null): PhotoIntervention
    {
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid photo type "%s". Allowed: %s', $type, implode(', ', self::ALLOWED_TYPES)));
        }

        if (!in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw new \InvalidArgumentException('Only JPEG, PNG, and WebP images are allowed');
        }

        $this->validateMagicBytes($file);

        if ($file->getSize() > self::MAX_SIZE) {
            throw new \InvalidArgumentException('File too large (max 10MB)');
        }

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = $this->slugger->slug($originalName);
        $filename = $safeName . '-' . bin2hex(random_bytes(8)) . '.' . $file->guessExtension();

        $uploadDir = $this->projectDir . '/var/photos';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $file->move($uploadDir, $filename);

        $filePath = $uploadDir . '/' . $filename;

        $photo = new PhotoIntervention();
        $photo->setRendezVous($rdv);
        $photo->setFilename($filename);
        $photo->setOriginalName($file->getClientOriginalName());
        $photo->setDescription($description);
        if ($annotationJson !== null) {
            json_decode($annotationJson);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('annotation_json must be valid JSON');
            }
        }
        $photo->setAnnotationJson($annotationJson);
        $photo->setAtelierId($rdv->getAtelierId());
        $photo->setType($type);
        $photo->setSha256($this->computeHash($filePath));
        $photo->setExif($this->extractExif($filePath));
        $photo->setTakenAt(new \DateTime());

        $this->em->persist($photo);
        $this->em->flush();

        return $photo;
    }

    private function validateMagicBytes(UploadedFile $file): void
    {
        $handle = fopen($file->getPathname(), 'rb');
        if ($handle === false) {
            throw new \InvalidArgumentException('Unable to read uploaded file');
        }
        $header = fread($handle, 12);
        fclose($handle);

        foreach (self::MAGIC_BYTES as $mime => $signatures) {
            foreach ($signatures as $sig) {
                if (str_starts_with($header, $sig)) {
                    return;
                }
            }
        }

        throw new \InvalidArgumentException('File content does not match allowed image formats');
    }

    public function computeHash(string $path): string
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("File not found: $path");
        }

        return hash_file('sha256', $path);
    }

    public function extractExif(string $path): ?array
    {
        if (!function_exists('exif_read_data')) {
            return null;
        }

        $mime = mime_content_type($path);
        if (!in_array($mime, ['image/jpeg', 'image/tiff'], true)) {
            return null;
        }

        try {
            $exif = @exif_read_data($path, 'ANY_TAG', true);
            if ($exif === false) {
                return null;
            }

            // Extract only useful fields
            $result = [];
            if (isset($exif['EXIF']['DateTimeOriginal'])) {
                $result['dateTimeOriginal'] = $exif['EXIF']['DateTimeOriginal'];
            }
            if (isset($exif['IFD0']['Make'])) {
                $result['make'] = $exif['IFD0']['Make'];
            }
            if (isset($exif['IFD0']['Model'])) {
                $result['model'] = $exif['IFD0']['Model'];
            }
            if (isset($exif['COMPUTED']['Width'])) {
                $result['width'] = $exif['COMPUTED']['Width'];
            }
            if (isset($exif['COMPUTED']['Height'])) {
                $result['height'] = $exif['COMPUTED']['Height'];
            }
            if (isset($exif['GPS'])) {
                $result['gps'] = [
                    'latitude' => $exif['GPS']['GPSLatitude'] ?? null,
                    'longitude' => $exif['GPS']['GPSLongitude'] ?? null,
                ];
            }

            return $result ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Check if a transition can proceed based on photo requirements.
     * Returns an array of missing requirements, empty if OK.
     */
    public function requirePhotosForTransition(string $transition, RendezVous $rdv): array
    {
        if (!isset(self::TRANSITION_PHOTO_REQUIREMENTS[$transition])) {
            return [];
        }

        $req = self::TRANSITION_PHOTO_REQUIREMENTS[$transition];
        $requiredType = $req['type'];
        $minCount = $req['min'];

        $count = $this->em->getRepository(PhotoIntervention::class)->count([
            'rendezVous' => $rdv,
            'type' => $requiredType,
        ]);

        if ($count >= $minCount) {
            return [];
        }

        return [
            'type' => $requiredType,
            'required' => $minCount,
            'current' => $count,
            'missing' => $minCount - $count,
        ];
    }
}
