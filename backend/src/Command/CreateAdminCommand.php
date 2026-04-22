<?php
namespace App\Command;

use App\Entity\Atelier;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-admin', description: 'Create the super admin user and default atelier')]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Check if admin already exists
        $existing = $this->em->getRepository(User::class)->findOneBy(['username' => 'admin']);
        if ($existing) {
            $io->warning('Admin user already exists.');
            return Command::SUCCESS;
        }

        // Create default atelier if needed
        $atelier = $this->em->getRepository(Atelier::class)->find(1);
        if (!$atelier) {
            $atelier = new Atelier();
            $atelier->setNom('Atelier Principal');
            $atelier->setSlug('atelier-principal');
            $this->em->persist($atelier);
            $this->em->flush();
            $io->info('Default atelier created (id=1).');
        }

        $adminPassword = trim((string) ($_ENV['ADMIN_PASSWORD'] ?? ''));
        if ($adminPassword === '') {
            $io->error('ADMIN_PASSWORD must be set before running app:create-admin.');
            return Command::FAILURE;
        }

        $user = new User();
        $user->setUsername('admin');
        $user->setEmail($_ENV['ADMIN_EMAIL'] ?? 'admin@atelier.local');
        $user->setRole('super_admin');
        $user->setAtelierId($atelier->getId());
        $user->setIsActive(1);

        $hashed = $this->hasher->hashPassword($user, $adminPassword);
        $user->setHashedPassword($hashed);

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('Super admin created: admin / %s', $adminPassword));

        return Command::SUCCESS;
    }
}
