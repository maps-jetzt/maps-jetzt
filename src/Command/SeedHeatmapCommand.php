<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\Heatmap;
use App\Entity\HeatmapPolyline;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:seed-heatmap',
    description: 'Generate test polylines in Hamburg for a heatmap',
)]
class SeedHeatmapCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('identifier', InputArgument::REQUIRED, 'Heatmap identifier')
            ->addOption('tracks', 't', InputOption::VALUE_OPTIONAL, 'Number of tracks to generate', 200);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $identifier = $input->getArgument('identifier');
        $trackCount = (int) $input->getOption('tracks');
        $conn = $this->em->getConnection();

        $heatmap = $this->em->getRepository(Heatmap::class)->findOneBy(['identifier' => $identifier]);
        if (!$heatmap) {
            $heatmap = new Heatmap();
            $heatmap->setIdentifier($identifier);
            $heatmap->setCenterLon(10.0);
            $heatmap->setCenterLat(53.55);
            $heatmap->setZoom(13);
            $this->em->persist($heatmap);
            $this->em->flush();
            $output->writeln("Created heatmap <info>{$identifier}</info>");
        } else {
            $output->writeln("Using existing heatmap <info>{$identifier}</info>");
        }

        // Popular route corridors in Hamburg (lon/lat pairs)
        // Each corridor is a base route; tracks are generated with random jitter
        $corridors = [
            // Outer Alster loop (very popular - many runners/cyclists)
            'alster_loop' => [
                'weight' => 40,
                'points' => [
                    [10.0000, 53.5600], [10.0050, 53.5650], [10.0100, 53.5700],
                    [10.0150, 53.5730], [10.0130, 53.5770], [10.0070, 53.5790],
                    [10.0000, 53.5800], [9.9930, 53.5790], [9.9870, 53.5770],
                    [9.9850, 53.5730], [9.9900, 53.5700], [9.9950, 53.5650],
                    [10.0000, 53.5600],
                ],
            ],
            // Inner Alster (also popular)
            'inner_alster' => [
                'weight' => 25,
                'points' => [
                    [9.9950, 53.5530], [9.9960, 53.5560], [9.9990, 53.5590],
                    [10.0030, 53.5600], [10.0050, 53.5580], [10.0040, 53.5550],
                    [10.0010, 53.5530], [9.9970, 53.5520], [9.9950, 53.5530],
                ],
            ],
            // Elbe waterfront (Landungsbruecken to Oevelgoenne)
            'elbe_west' => [
                'weight' => 30,
                'points' => [
                    [9.9700, 53.5460], [9.9620, 53.5450], [9.9540, 53.5445],
                    [9.9450, 53.5440], [9.9350, 53.5445], [9.9250, 53.5450],
                    [9.9150, 53.5455], [9.9050, 53.5460], [9.8950, 53.5470],
                ],
            ],
            // Elbe east (HafenCity to Entenwerder)
            'elbe_east' => [
                'weight' => 20,
                'points' => [
                    [9.9900, 53.5410], [9.9970, 53.5390], [10.0050, 53.5380],
                    [10.0150, 53.5370], [10.0250, 53.5365], [10.0350, 53.5370],
                    [10.0400, 53.5380],
                ],
            ],
            // Stadtpark loop
            'stadtpark' => [
                'weight' => 25,
                'points' => [
                    [10.0400, 53.5960], [10.0430, 53.5980], [10.0470, 53.5990],
                    [10.0510, 53.5985], [10.0530, 53.5960], [10.0510, 53.5940],
                    [10.0470, 53.5930], [10.0430, 53.5935], [10.0400, 53.5960],
                ],
            ],
            // Alster north-south (commuter route)
            'alster_ns' => [
                'weight' => 15,
                'points' => [
                    [10.0000, 53.5500], [10.0010, 53.5550], [10.0000, 53.5600],
                    [9.9990, 53.5650], [10.0000, 53.5700], [10.0010, 53.5750],
                    [10.0000, 53.5800],
                ],
            ],
            // Eppendorf - Winterhude connector
            'eppendorf' => [
                'weight' => 15,
                'points' => [
                    [9.9800, 53.5800], [9.9850, 53.5810], [9.9920, 53.5820],
                    [10.0000, 53.5810], [10.0050, 53.5800], [10.0100, 53.5790],
                ],
            ],
            // Planten un Blomen - Reeperbahn
            'stpauli' => [
                'weight' => 20,
                'points' => [
                    [9.9800, 53.5570], [9.9780, 53.5550], [9.9760, 53.5530],
                    [9.9740, 53.5510], [9.9720, 53.5490], [9.9700, 53.5470],
                ],
            ],
            // Altona - Ottensen
            'altona' => [
                'weight' => 10,
                'points' => [
                    [9.9350, 53.5520], [9.9380, 53.5540], [9.9420, 53.5560],
                    [9.9470, 53.5570], [9.9520, 53.5560], [9.9560, 53.5540],
                ],
            ],
        ];

        // Compute total weight for probability distribution
        $totalWeight = array_sum(array_column($corridors, 'weight'));

        $inserted = 0;
        for ($i = 0; $i < $trackCount; $i++) {
            // Pick a random corridor weighted by popularity
            $rand = mt_rand(1, $totalWeight);
            $cumulative = 0;
            $selectedPoints = [];
            foreach ($corridors as $corridor) {
                $cumulative += $corridor['weight'];
                if ($rand <= $cumulative) {
                    $selectedPoints = $corridor['points'];
                    break;
                }
            }

            // Generate a jittered variant of this corridor
            // Some tracks use the full route, some use a random sub-section
            $startIdx = 0;
            $endIdx = count($selectedPoints) - 1;
            if (count($selectedPoints) > 3 && mt_rand(0, 2) > 0) {
                $startIdx = mt_rand(0, (int) floor(count($selectedPoints) / 3));
                $endIdx = mt_rand((int) ceil(2 * count($selectedPoints) / 3), count($selectedPoints) - 1);
            }

            $jitter = 0.0003 + (mt_rand(0, 100) / 100000); // ~30-130m jitter
            $coords = [];
            for ($j = $startIdx; $j <= $endIdx; $j++) {
                $lon = $selectedPoints[$j][0] + (mt_rand(-100, 100) / 100) * $jitter;
                $lat = $selectedPoints[$j][1] + (mt_rand(-100, 100) / 100) * $jitter;
                $coords[] = sprintf('%.6f %.6f', $lon, $lat);
            }

            if (count($coords) < 2) {
                continue;
            }

            $wkt = 'LINESTRING(' . implode(', ', $coords) . ')';
            $hash = md5($wkt . $i);

            try {
                $ewkt = $conn->fetchOne(
                    'SELECT ST_AsEWKT(ST_Transform(ST_GeomFromText(:wkt, 4326), 3857))',
                    ['wkt' => $wkt]
                );

                $polyline = new HeatmapPolyline();
                $polyline->setHeatmap($heatmap);
                $polyline->setHash($hash);
                $polyline->setGeom($ewkt);
                $this->em->persist($polyline);
                $inserted++;

                if ($inserted % 50 === 0) {
                    $this->em->flush();
                    $output->writeln("  Inserted {$inserted}/{$trackCount} tracks...");
                }
            } catch (\Exception $e) {
                $output->writeln("<error>Track {$i} failed: {$e->getMessage()}</error>");
            }
        }

        $this->em->flush();
        $output->writeln("Done! Inserted <info>{$inserted}</info> tracks into heatmap <info>{$identifier}</info>.");

        return Command::SUCCESS;
    }
}
