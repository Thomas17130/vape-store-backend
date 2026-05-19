<?php

namespace App\Command;

use App\Entity\Box;
use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Eliquid;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\ProductVariant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:catalog:faker',
    description: 'Generate fake catalog data for e-liquids, boxes and kits.',
)]
class CatalogFakerCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('e-liquids', null, InputOption::VALUE_REQUIRED, 'Number of fake e-liquids', 16)
            ->addOption('boxes', null, InputOption::VALUE_REQUIRED, 'Number of fake boxes', 14)
            ->addOption('kits', null, InputOption::VALUE_REQUIRED, 'Number of fake kits', 12);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $eliquidCount = max(0, (int) $input->getOption('e-liquids'));
        $boxCount = max(0, (int) $input->getOption('boxes'));
        $kitCount = max(0, (int) $input->getOption('kits'));

        $categories = [
            'eliquids' => $this->findOrCreateCategory('E-Liquids', 'e-liquids'),
            'boxes' => $this->findOrCreateCategory('Boxes', 'boxes'),
            'kits' => $this->findOrCreateCategory('Kits', 'kits'),
        ];

        $brandNames = ['Vaporesso', 'Geekvape', 'Voopoo', 'Smok', 'Innokin', 'Aspire', 'Dinner Lady', 'Alfaliquid'];
        $brands = array_map(fn (string $name) => $this->findOrCreateBrand($name), $brandNames);

        for ($i = 0; $i < $eliquidCount; $i++) {
            $this->createFakeEliquid($brands, $categories['eliquids'], $i);
        }

        for ($i = 0; $i < $boxCount; $i++) {
            $this->createFakeBox($brands, $categories['boxes'], $i);
        }

        for ($i = 0; $i < $kitCount; $i++) {
            $this->createFakeKit($brands, $categories['boxes'], $categories['kits'], $i);
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Fake catalog inserted: %d e-liquids, %d boxes, %d kits.',
            $eliquidCount,
            $boxCount,
            $kitCount
        ));

        return Command::SUCCESS;
    }

    /**
     * @param list<Brand> $brands
     */
    private function createFakeEliquid(array $brands, Category $category, int $index): void
    {
        $flavors = ['Mango Ice', 'Blueberry Burst', 'Classic Tobacco', 'Mint Frost', 'Vanilla Custard', 'Red Fruits'];
        $ratios = ['50/50', '70/30', '60/40'];
        $nicotine = [0, 3, 6, 9, 12];
        $volumes = [10, 30, 50, 100];

        $flavor = $flavors[array_rand($flavors)];
        $ratio = $ratios[array_rand($ratios)];
        $nic = $nicotine[array_rand($nicotine)];
        $volume = $volumes[array_rand($volumes)];

        $name = sprintf('%s %dml %dmg', $flavor, $volume, $nic);
        $price = random_int(499, 1899);
        $salePrice = random_int(0, 1) === 1 ? random_int((int) max(250, $price * 0.6), $price - 50) : null;

        $product = new Eliquid();
        $product
            ->setName($name)
            ->setDescription(sprintf('Premium e-liquid %s with a balanced %s base.', strtolower($flavor), $ratio))
            ->setSku($this->uniqueSku('ELQ'))
            ->setSlug($this->uniqueSlug($name))
            ->setPrice($price)
            ->setSalePrice($salePrice)
            ->setQuantity(random_int(8, 240))
            ->setVolume($volume)
            ->setSeenCount(random_int(35, 4200))
            ->setBrand($brands[array_rand($brands)])
            ->setIsActive(true)
            ->setCreatedAt($this->randomDateWithinDays(140))
            ->setUpdatedAt(new \DateTimeImmutable());

        $product->addCategory($category);

        $this->attachMediaAndVariants($product, 'e-liquid', $index, [
            ['title' => sprintf('%dmg / %dml', $nic, $volume), 'attributes' => ['nicotine' => $nic.'mg', 'ratio' => $ratio]],
            ['title' => sprintf('%dmg / %dml - Ice', $nic, $volume), 'attributes' => ['nicotine' => $nic.'mg', 'ratio' => $ratio, 'cooling' => 'yes']],
        ]);

        $this->entityManager->persist($product);
    }

    /**
     * @param list<Brand> $brands
     */
    private function createFakeBox(array $brands, Category $category, int $index): void
    {
        $series = ['Pulse', 'Drag', 'Gen', 'Aegis', 'Xros', 'Nano'];
        $name = sprintf('%s Box Mod %dW', $series[array_rand($series)], random_int(40, 220));
        $price = random_int(2890, 7990);
        $salePrice = random_int(0, 1) === 1 ? random_int((int) max(1200, $price * 0.65), $price - 150) : null;

        $product = new Box();
        $product
            ->setName($name)
            ->setDescription('Compact vape box with stable power delivery and quick charging.')
            ->setSku($this->uniqueSku('BOX'))
            ->setSlug($this->uniqueSlug($name))
            ->setPrice($price)
            ->setSalePrice($salePrice)
            ->setQuantity(random_int(4, 90))
            ->setTypeBattery(random_int(1, 3))
            ->setSeenCount(random_int(75, 5200))
            ->setBrand($brands[array_rand($brands)])
            ->setIsActive(true)
            ->setCreatedAt($this->randomDateWithinDays(200))
            ->setUpdatedAt(new \DateTimeImmutable());

        $product->addCategory($category);

        $this->attachMediaAndVariants($product, 'box', $index, [
            ['title' => 'Standard Edition', 'attributes' => ['finish' => 'matte', 'chipset' => 'v2']],
            ['title' => 'Carbon Edition', 'attributes' => ['finish' => 'carbon', 'chipset' => 'v2']],
        ]);

        $this->entityManager->persist($product);
    }

    /**
     * @param list<Brand> $brands
     */
    private function createFakeKit(array $brands, Category $boxesCategory, Category $kitsCategory, int $index): void
    {
        $series = ['Starter', 'Pro', 'All-Day', 'Cloud', 'Stealth', 'Prime'];
        $name = sprintf('%s Vape Kit %d', $series[array_rand($series)], random_int(100, 999));
        $price = random_int(3990, 10990);
        $salePrice = random_int(0, 1) === 1 ? random_int((int) max(1800, $price * 0.65), $price - 200) : null;

        $product = new Box();
        $product
            ->setName($name)
            ->setDescription('Complete vape kit with pod tank, coils and charging cable included.')
            ->setSku($this->uniqueSku('KIT'))
            ->setSlug($this->uniqueSlug($name))
            ->setPrice($price)
            ->setSalePrice($salePrice)
            ->setQuantity(random_int(3, 70))
            ->setTypeBattery(random_int(1, 3))
            ->setSeenCount(random_int(120, 7800))
            ->setBrand($brands[array_rand($brands)])
            ->setIsActive(true)
            ->setCreatedAt($this->randomDateWithinDays(120))
            ->setUpdatedAt(new \DateTimeImmutable());

        $product->addCategory($boxesCategory);
        $product->addCategory($kitsCategory);

        $this->attachMediaAndVariants($product, 'kit', $index, [
            ['title' => 'Kit Standard', 'attributes' => ['contents' => 'mod+pod+coil', 'coil' => '0.6ohm']],
            ['title' => 'Kit Extended', 'attributes' => ['contents' => 'mod+pod+2coil', 'coil' => '0.4ohm']],
        ]);

        $this->entityManager->persist($product);
    }

    /**
     * @param list<array{title: string, attributes: array<string, string>}> $variants
     */
    private function attachMediaAndVariants(Product $product, string $seedPrefix, int $index, array $variants): void
    {
        $image = new ProductImage();
        $image->setProduct($product);
        $image->setUrl(sprintf('https://picsum.photos/seed/%s-%d/640/640', $seedPrefix, $index + random_int(1, 9999)));
        $image->setAltText($product->getName());
        $image->setPosition(0);
        $image->setIsPrimary(true);
        $product->addImage($image);
        $this->entityManager->persist($image);

        foreach ($variants as $variantIndex => $variantData) {
            $variant = new ProductVariant();
            $variant->setProduct($product);
            $variant->setSku($this->uniqueSku('VAR'));
            $variant->setTitle($variantData['title']);
            $variant->setAttributes($variantData['attributes']);
            $variant->setPrice(max(100, (int) (($product->getSalePrice() ?? $product->getPrice()) + random_int(-300, 600))));
            $variant->setQuantity(max(0, (int) $product->getQuantity() + random_int(-5, 15)));
            $variant->setIsDefault($variantIndex === 0);

            $product->addVariant($variant);
            $this->entityManager->persist($variant);
        }
    }

    private function findOrCreateBrand(string $name): Brand
    {
        $brand = $this->entityManager->getRepository(Brand::class)->findOneBy(['name' => $name]);
        if ($brand instanceof Brand) {
            return $brand;
        }

        $brand = new Brand();
        $brand->setName($name);
        $this->entityManager->persist($brand);

        return $brand;
    }

    private function findOrCreateCategory(string $name, string $slug): Category
    {
        $category = $this->entityManager->getRepository(Category::class)->findOneBy(['slug' => $slug]);
        if ($category instanceof Category) {
            return $category;
        }

        $category = new Category();
        $category->setName($name);
        $category->setSlug($slug);
        $this->entityManager->persist($category);

        return $category;
    }

    private function uniqueSku(string $prefix): string
    {
        do {
            $sku = sprintf('%s-%s', strtoupper($prefix), strtoupper(bin2hex(random_bytes(3))));
            $exists = $this->entityManager->getRepository(Product::class)->findOneBy(['sku' => $sku]);
        } while ($exists instanceof Product);

        return $sku;
    }

    private function uniqueSlug(string $base): string
    {
        $seed = mb_strtolower($base);
        $seed = preg_replace('/[^a-z0-9]+/i', '-', $seed) ?? 'item';
        $seed = trim($seed, '-');
        if ($seed === '') {
            $seed = 'item';
        }

        do {
            $candidate = sprintf('%s-%s', $seed, strtolower(bin2hex(random_bytes(2))));
            $exists = $this->entityManager->getRepository(Product::class)->findOneBy(['slug' => $candidate]);
        } while ($exists instanceof Product);

        return $candidate;
    }

    private function randomDateWithinDays(int $days): \DateTimeImmutable
    {
        return (new \DateTimeImmutable())->sub(new \DateInterval(sprintf('P%dD', random_int(0, max(1, $days)))));
    }
}
