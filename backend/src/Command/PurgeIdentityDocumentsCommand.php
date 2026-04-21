<?php

namespace App\Command;

use App\Service\AuditService;
use App\Service\VODocumentService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:purge-identity-documents',
    description: 'Purge identity documents with 0-day retention after LP transcription (RGPD compliance)',
)]
class PurgeIdentityDocumentsCommand extends Command
{
    public function __construct(
        private VODocumentService $documentService,
        private AuditService $auditService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $beforeCount = $this->documentService->countStoredSensitiveIdentityDocuments();
        if ($beforeCount > 0) {
            $io->note(sprintf('%d document(s) sensible(s) encore stocké(s) avant purge.', $beforeCount));
        }

        $count = $this->documentService->purgeExpiredIdentityDocuments();

        if ($count > 0) {
            $this->auditService->log(
                'rgpd_purge_identity',
                'VODocument',
                null,
                sprintf('%d pièce(s) d\'identité / justificatif(s) de domicile purgé(s)', $count),
            );
        }

        $io->success(sprintf('%d document(s) purgé(s).', $count));

        return Command::SUCCESS;
    }
}
