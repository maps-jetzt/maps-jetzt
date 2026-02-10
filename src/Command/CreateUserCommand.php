<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Create a new API user',
)]
class CreateUserCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email address');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = $input->getArgument('email');

        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            $output->writeln("<error>User with email {$email} already exists</error>");
            return Command::FAILURE;
        }

        $apiKey = bin2hex(random_bytes(32));

        $user = new User();
        $user->setEmail($email);
        $user->setApiKey($apiKey);

        $this->em->persist($user);
        $this->em->flush();

        $output->writeln("User created: <info>{$email}</info>");
        $output->writeln("API Key:      <info>{$apiKey}</info>");

        return Command::SUCCESS;
    }
}
