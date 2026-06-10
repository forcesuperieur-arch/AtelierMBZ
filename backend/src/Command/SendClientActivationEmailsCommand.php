<?php

namespace App\Command;

use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:client:send-activation-emails',
    description: 'Envoie un email d\'activation du portail à tous les clients sans mot de passe.',
)]
class SendClientActivationEmailsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('base-url', null, InputOption::VALUE_REQUIRED, 'URL publique du portail client', 'http://localhost:81/client');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $baseUrl = rtrim($input->getOption('base-url'), '/');

        $clients = $this->em->getRepository(Client::class)->createQueryBuilder('c')
            ->where('c.password IS NULL OR c.password = :empty')
            ->andWhere('c.email IS NOT NULL')
            ->andWhere('c.isAnonymized = false')
            ->setParameter('empty', '')
            ->getQuery()
            ->getResult();

        $sent = 0;
        $skipped = 0;

        foreach ($clients as $client) {
            $token = bin2hex(random_bytes(32));
            $client->setResetToken($token);
            $client->setResetTokenExpiresAt(new \DateTime('+7 days'));

            $resetUrl = $baseUrl . '/reset-password?token=' . $token;

            $email = (new Email())
                ->from('noreply@paddock.fr')
                ->to($client->getEmail())
                ->subject('Activez votre espace client')
                ->html(sprintf(
                    '<p>Bonjour %s,</p>' .
                    '<p>Votre atelier vous donne accès à votre espace client en ligne.</p>' .
                    '<p>Cliquez sur le lien ci-dessous pour définir votre mot de passe :</p>' .
                    '<p><a href="%s">%s</a></p>' .
                    '<p>Ce lien est valable 7 jours.</p>' .
                    '<p>Cordialement,<br>L\'équipe Paddock</p>',
                    htmlspecialchars($client->getPrenom() ?? ''),
                    htmlspecialchars($resetUrl),
                    htmlspecialchars($resetUrl)
                ));

            $this->mailer->send($email);
            $sent++;
        }

        $this->em->flush();

        $output->writeln(sprintf('Envoyé : %d | Passé : %d', $sent, $skipped));

        return Command::SUCCESS;
    }
}
