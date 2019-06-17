<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Aggregation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\StatsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Test\TestCaseBase\AggregationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class StatsAggregationTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AggregationTestBehaviour;

    public function testStatsAggregationNeedsSetup(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('StatsAggregation configured without fetch');

        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addAggregation(new StatsAggregation('taxRate', 'rate_agg', false, false, false, false, false));

        $taxRepository = $this->getContainer()->get('tax.repository');
        $taxRepository->aggregate($criteria, $context);
    }

    public function testStatsAggregation(): void
    {
        $context = Context::createDefaultContext();
        $ids = $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $ids));
        $criteria->addAggregation(new StatsAggregation('taxRate', 'rate_agg'));

        $taxRepository = $this->getContainer()->get('tax.repository');
        $result = $taxRepository->aggregate($criteria, $context);

        /** @var \Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult $rateAgg */
        $rateAgg = $result->getAggregations()->get('rate_agg');
        static::assertNotNull($rateAgg);

        static::assertEquals(
            [
                new StatsResult(null, 10, 90, 8, 32.5, 260),
            ], $rateAgg->getResult()
        );
    }

    public function testStatsAggregationShouldNullNotRequestedValues(): void
    {
        $context = Context::createDefaultContext();
        $ids = $this->setupFixtures($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $ids));
        $criteria->addAggregation(new StatsAggregation('taxRate', 'rate_agg', false, true, false, true, false));

        $taxRepository = $this->getContainer()->get('tax.repository');
        $result = $taxRepository->aggregate($criteria, $context);

        /** @var \Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult $rateAgg */
        $rateAgg = $result->getAggregations()->get('rate_agg');
        static::assertNotNull($rateAgg);

        /** @var StatsResult $result */
        $result = $rateAgg->get(null);

        static::assertNull($result->getKey());
        static::assertEquals(10, $result->getMin());
        static::assertEquals(32.5, $result->getAvg());
    }

    public function testStatsAggregationWithGroupBy(): void
    {
        $context = Context::createDefaultContext();
        $ids = $this->setupGroupByFixtures($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.categories.id', $ids));
        $criteria->addAggregation(new StatsAggregation('product.price.gross', 'stats_agg', true, true, true, true, true, 'product.categories.name'));

        $productRepository = $this->getContainer()->get('product.repository');
        $result = $productRepository->aggregate($criteria, $context);

        /** @var AggregationResult $statsAgg */
        $statsAgg = $result->getAggregations()->get('stats_agg');
        static::assertCount(4, $statsAgg->getResult());
        static::assertEquals(10, $statsAgg->get(['product.categories.name' => 'cat1'])->getMin());
        static::assertEquals(20, $statsAgg->get(['product.categories.name' => 'cat2'])->getMin());
        static::assertEquals(10, $statsAgg->get(['product.categories.name' => 'cat3'])->getMin());
        static::assertEquals(10, $statsAgg->get(['product.categories.name' => 'cat4'])->getMin());

        static::assertEquals(20, $statsAgg->get(['product.categories.name' => 'cat1'])->getMax());
        static::assertEquals(90, $statsAgg->get(['product.categories.name' => 'cat2'])->getMax());
        static::assertEquals(90, $statsAgg->get(['product.categories.name' => 'cat3'])->getMax());
        static::assertEquals(20, $statsAgg->get(['product.categories.name' => 'cat4'])->getMax());

        static::assertEquals(3, $statsAgg->get(['product.categories.name' => 'cat1'])->getCount());
        static::assertEquals(3, $statsAgg->get(['product.categories.name' => 'cat2'])->getCount());
        static::assertEquals(3, $statsAgg->get(['product.categories.name' => 'cat3'])->getCount());
        static::assertEquals(2, $statsAgg->get(['product.categories.name' => 'cat4'])->getCount());

        static::assertEqualsWithDelta(13.33, $statsAgg->get(['product.categories.name' => 'cat1'])->getAvg(), 0.01);
        static::assertEqualsWithDelta(53.33, $statsAgg->get(['product.categories.name' => 'cat2'])->getAvg(), 0.01);
        static::assertEquals(50, $statsAgg->get(['product.categories.name' => 'cat3'])->getAvg());
        static::assertEquals(15, $statsAgg->get(['product.categories.name' => 'cat4'])->getAvg());

        static::assertEquals(40, $statsAgg->get(['product.categories.name' => 'cat1'])->getSum());
        static::assertEquals(160, $statsAgg->get(['product.categories.name' => 'cat2'])->getSum());
        static::assertEquals(150, $statsAgg->get(['product.categories.name' => 'cat3'])->getSum());
        static::assertEquals(30, $statsAgg->get(['product.categories.name' => 'cat4'])->getSum());
    }
}
