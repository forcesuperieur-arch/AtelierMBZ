<?php

namespace App\Command;

use App\Entity\Devis;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:devis-expire',
    description: 'Passe en statut "expire" les devis brouillon/envoyé dont la date de validité est dépassée',
)]
class DevisExpireCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $devisExpires = $this->em->createQueryBuilder()
            ->select('d')
            ->from(Devis::class, 'd')
            ->where('d.statut IN (:statuts)')
            ->andWhere('d.dateValidite < :now')
            ->setParameter('statuts', ['brouillon', 'envoye'])
            ->setParameter('now', new \DateTime())
            ->getQuery()->getResult();

        foreach ($devisExpires as $devis) {
            $devis->setStatut('expire');
        }
        $this->em->flush();

        $io->success(sprintf('%d devis passés en statut "expire".', count($devisExpires)));

        return Command::SUCCESS;
    }
}
