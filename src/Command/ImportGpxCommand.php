<?php declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use phpGPX\phpGPX;

#[AsCommand(name: 'app:import-gpx')]
class ImportGpxCommand extends Command
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        parent::__construct();
        $this->connection = $connection;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Importiert eine GPX-Datei in die PostGIS-Datenbank.')
            ->addArgument('file', InputArgument::REQUIRED, 'Pfad zur GPX-Datei');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getArgument('file');

        if (!file_exists($filePath)) {
            throw new \Exception("Die Datei $filePath wurde nicht gefunden.");
        }

        $gpx = new phpGPX();
        $file = $gpx->load($filePath);

        foreach ($file->tracks as $track) {
            foreach ($track->segments as $segment) {
                $coordinates = array_map(fn($point) => "{$point->longitude} {$point->latitude}", $segment->points);
                $lineString = 'LINESTRING(' . implode(', ', $coordinates) . ')';

                $this->connection->executeStatement(
                    'INSERT INTO gpx_tracks (name, geom) VALUES (:name, ST_GeomFromText(:geom, 4326))',
                    [
                        'name' => $track->name ?? 'Unnamed Track',
                        'geom' => $lineString,
                    ]
                );
            }
        }

        $output->writeln('<info>GPX-Daten erfolgreich importiert.</info>');
        return Command::SUCCESS;
    }
}
