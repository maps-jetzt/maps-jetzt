<?php declare(strict_types=1);

namespace App\Command;
namespace App\Command;

use App\Entity\Activity;
use Doctrine\ORM\EntityManagerInterface;
use phpGPX\phpGPX;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-gpx',
    description: 'Importiert alle GPX-Dateien aus einem Ordner in die Datenbank',
)]
class ImportGpxCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('directory', InputArgument::REQUIRED, 'Pfad zum Ordner mit den GPX-Dateien');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $directory = $input->getArgument('directory');

        if (!is_dir($directory)) {
            $output->writeln('<error>Der angegebene Ordner existiert nicht.</error>');
            return Command::FAILURE;
        }

        $gpxFiles = glob($directory . '/*.gpx');
        if (empty($gpxFiles)) {
            $output->writeln('<comment>Keine GPX-Dateien im angegebenen Ordner gefunden.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<info>%d GPX-Dateien gefunden. Beginne mit dem Import...</info>', count($gpxFiles)));
        $gpxParser = new phpGPX();

        $counter = 0;

        $io->progressStart(count($gpxFiles));

        foreach ($gpxFiles as $file) {
            if ($counter % 100 === 0) {
                $this->entityManager->flush();
            }

            $output->writeln(sprintf('Importiere Datei: %s', basename($file)));
            $io->progressAdvance();

            try {
                $this->importGpxFile($file, $gpxParser);
                $output->writeln('<info>Erfolgreich importiert.</info>');

                ++$counter;
            } catch (\Exception $e) {
                $output->writeln(sprintf('<error>Fehler beim Import der Datei %s: %s</error>', basename($file), $e->getMessage()));
            }
        }

        $io->progressFinish();

        $this->entityManager->flush();
        $output->writeln('<info>Alle Dateien wurden verarbeitet.</info>');

        return Command::SUCCESS;
    }

    private function importGpxFile(string $filePath, phpGPX $gpxParser): void
    {
        $gpxFile = $gpxParser->load($filePath);

        foreach ($gpxFile->tracks as $track) {
            $points = [];
            foreach ($track->segments as $segment) {
                foreach ($segment->points as $point) {
                    $points[] = [$point->longitude, $point->latitude];
                }
            }

            if (empty($points)) {
                continue;
            }

            // LINESTRING erstellen
            $lineStringWkt = 'LINESTRING(' . implode(', ', array_map(fn($p) => implode(' ', $p), $points)) . ')';

            $activity = new Activity();
            $activity->setName($track->name ?: basename($filePath, '.gpx'));
            $activity->setDescription($track->description ?: '');
            $activity->setGeom($lineStringWkt);

            $this->entityManager->persist($activity);
        }
    }
}

