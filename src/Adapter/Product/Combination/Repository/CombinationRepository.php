<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace PrestaShop\PrestaShop\Adapter\Product\Combination\Repository;

use Combination;
use Db;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Adapter\AbstractObjectModelRepository;
use PrestaShop\PrestaShop\Adapter\Attribute\Repository\AttributeRepository;
use PrestaShop\PrestaShop\Adapter\Product\Combination\Validate\CombinationValidator;
use PrestaShop\PrestaShop\Core\Domain\Language\ValueObject\LanguageId;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Exception\CannotAddCombinationException;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Exception\CannotBulkDeleteCombinationException;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Exception\CannotDeleteCombinationException;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Exception\CombinationNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\ValueObject\CombinationId;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductId;
use PrestaShop\PrestaShop\Core\Exception\CoreException;
use PrestaShopException;
use Product;

/**
 * Provides access to Combination data source
 */
class CombinationRepository extends AbstractObjectModelRepository
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var string
     */
    private $dbPrefix;

    /**
     * @var AttributeRepository
     */
    private $attributeRepository;

    /**
     * @var CombinationValidator
     */
    private $combinationValidator;

    /**
     * @param Connection $connection
     * @param string $dbPrefix
     * @param AttributeRepository $attributeRepository
     * @param CombinationValidator $combinationValidator
     */
    public function __construct(
        Connection $connection,
        string $dbPrefix,
        AttributeRepository $attributeRepository,
        CombinationValidator $combinationValidator
    ) {
        $this->connection = $connection;
        $this->dbPrefix = $dbPrefix;
        $this->attributeRepository = $attributeRepository;
        $this->combinationValidator = $combinationValidator;
    }

    /**
     * @param CombinationId $combinationId
     *
     * @return Combination
     *
     * @throws CombinationNotFoundException
     */
    public function get(CombinationId $combinationId): Combination
    {
        /** @var Combination $combination */
        $combination = $this->getObjectModel(
            $combinationId->getValue(),
            Combination::class,
            CombinationNotFoundException::class
        );

        return $combination;
    }

    /**
     * @param ProductId $productId
     * @param bool $isDefault
     *
     * @return Combination
     *
     * @throws CoreException
     */
    public function create(ProductId $productId, bool $isDefault): Combination
    {
        $combination = new Combination();
        $combination->id_product = $productId->getValue();
        $combination->default_on = $isDefault;

        $this->addObjectModel($combination, CannotAddCombinationException::class);

        return $combination;
    }

    /**
     * @param Combination $combination
     * @param array $updatableProperties
     * @param int $errorCode
     */
    public function partialUpdate(Combination $combination, array $updatableProperties, int $errorCode): void
    {
        $this->combinationValidator->validate($combination);
        $this->partiallyUpdateObjectModel(
            $combination,
            $updatableProperties,
            CannotAddCombinationException::class,
            $errorCode
        );
    }

    /**
     * @param CombinationId $combinationId
     * @param int $errorCode
     *
     * @throws CoreException
     */
    public function delete(CombinationId $combinationId, int $errorCode = 0): void
    {
        $this->deleteObjectModel($this->get($combinationId), CannotDeleteCombinationException::class, $errorCode);
    }

    /**
     * @param ProductId $productId
     *
     * @throws CannotDeleteCombinationException
     */
    public function deleteByProductId(ProductId $productId): void
    {
        $combinationIds = $this->getCombinationIdsByProductId($productId);

        $this->bulkDelete($combinationIds);
    }

    /**
     * @param array $combinationIds
     */
    public function bulkDelete(array $combinationIds): void
    {
        $failedIds = [];
        foreach ($combinationIds as $combinationId) {
            try {
                $this->delete($combinationId);
            } catch (CannotDeleteCombinationException $e) {
                $failedIds[] = $combinationId->getValue();
            }
        }

        if (empty($failedIds)) {
            return;
        }

        throw new CannotBulkDeleteCombinationException($failedIds, sprintf(
            'Failed to delete following combinations: %s',
            implode(', ', $failedIds)
        ));
    }

    /**
     * @param ProductId $productId
     *
     * @return CombinationId[]
     */
    private function getCombinationIdsByProductId(ProductId $productId): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('pa.id_product_attribute')
            ->from($this->dbPrefix . 'product_attribute', 'pa')
            ->andWhere('pa.id_product = :productId')
            ->setParameter('productId', $productId->getValue())
        ;
        $combinationIds = $qb->execute()->fetchAll();

        return array_map(
            function (array $combination) { return new CombinationId((int) $combination['id_product_attribute']); },
            $combinationIds
        );
    }

    /**
     * @param ProductId $productId
     * @param int|null $limit
     * @param int|null $offset
     * @param array<string, mixed> $filters
     *
     * @return array<int, array<string, mixed>>
     */
    public function getProductCombinations(ProductId $productId, ?int $limit = null, ?int $offset = null, array $filters = []): array
    {
        $qb = $this->getCombinationsQueryBuilder($productId, $filters)
            ->select('pa.*')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
        ;

        return $qb->execute()->fetchAll();
    }

    /**
     * @param ProductId $productId
     * @param array<string, array<int, int>> $filters
     *
     * @return int
     */
    public function getTotalCombinationsCount(ProductId $productId, array $filters = []): int
    {
        $qb = $this->getCombinationsQueryBuilder($productId, $filters)
            ->select('COUNT(pa.id_product_attribute) AS total_combinations')
        ;

        return (int) $qb->execute()->fetch()['total_combinations'];
    }

    /**
     * @param int[] $combinationIds
     * @param LanguageId $langId
     *
     * @return array<int, array<int, mixed>>
     */
    public function getAttributesInfoByCombinationIds(array $combinationIds, LanguageId $langId): array
    {
        $attributeCombinationAssociations = $this->getAttributeCombinationAssociations($combinationIds);

        $attributeIds = array_unique(array_map(function (array $attributeByCombination): int {
            return (int) $attributeByCombination['id_attribute'];
        }, $attributeCombinationAssociations));

        $attributesInfoByAttributeId = $this->getAttributesInformation($attributeIds, $langId->getValue());

        $attributesInfoByCombinationId = [];
        foreach ($attributeCombinationAssociations as $attributeCombinationAssociation) {
            $combinationId = (int) $attributeCombinationAssociation['id_product_attribute'];
            $attributeId = (int) $attributeCombinationAssociation['id_attribute'];
            $attributesInfoByCombinationId[$combinationId][] = $attributesInfoByAttributeId[$attributeId];
        }

        return $attributesInfoByCombinationId;
    }

    /**
     * @param CombinationId $combinationId
     *
     * @throws CoreException
     */
    public function assertCombinationExists(CombinationId $combinationId): void
    {
        $this->assertObjectModelExists(
            $combinationId->getValue(),
            'product_attribute',
            CombinationNotFoundException::class
        );
    }

    /**
     * @param CombinationId $combinationId
     * @param int[] $attributeIds
     */
    public function saveProductAttributeAssociation(CombinationId $combinationId, array $attributeIds): void
    {
        $this->assertCombinationExists($combinationId);
        $this->attributeRepository->assertAllAttributesExist($attributeIds);

        $attributesList = [];
        foreach ($attributeIds as $attributeId) {
            $attributesList[] = [
                'id_product_attribute' => $combinationId->getValue(),
                'id_attribute' => $attributeId,
            ];
        }

        try {
            if (!Db::getInstance()->insert('product_attribute_combination', $attributesList)) {
                throw new CannotAddCombinationException('Failed saving product-combination associations');
            }
        } catch (PrestaShopException $e) {
            throw new CoreException('Error occurred when saving product-combination associations', 0, $e);
        }
    }

    /**
     * @param ProductId $productId
     *
     * @return Combination|null
     *
     * @throws CoreException
     */
    public function findDefaultCombination(ProductId $productId): ?Combination
    {
        try {
            $id = (int) Product::getDefaultAttribute($productId->getValue(), 0, true);
        } catch (PrestaShopException $e) {
            throw new CoreException('Error occurred while trying to get product default combination', 0, $e);
        }

        return $id ? $this->get(new CombinationId($id)) : null;
    }

    /**
     * @param int[] $combinationIds
     *
     * @return array<int, array<string, mixed>>
     */
    private function getAttributeCombinationAssociations(array $combinationIds): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('pac.id_attribute')
            ->addSelect('pac.id_product_attribute')
            ->from($this->dbPrefix . 'product_attribute_combination', 'pac')
            ->where($qb->expr()->in('pac.id_product_attribute', ':combinationIds'))
            ->setParameter('combinationIds', $combinationIds, Connection::PARAM_INT_ARRAY)
        ;

        return $qb->execute()->fetchAll();
    }

    /**
     * @param int[] $attributeIds
     * @param int $langId
     *
     * @return array<int, array<int, mixed>>
     */
    private function getAttributesInformation(array $attributeIds, int $langId): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('a.id_attribute')
            ->addSelect('ag.id_attribute_group')
            ->addSelect('al.name AS attribute_name')
            ->addSelect('agl.name AS attribute_group_name')
            ->from($this->dbPrefix . 'attribute', 'a')
            ->leftJoin(
                'a',
                $this->dbPrefix . 'attribute_lang',
                'al',
                'a.id_attribute = al.id_attribute AND al.id_lang = :langId'
            )->leftJoin(
                'a',
                $this->dbPrefix . 'attribute_group',
                'ag',
                'a.id_attribute_group = ag.id_attribute_group'
            )->leftJoin(
                'ag',
                $this->dbPrefix . 'attribute_group_lang',
                'agl',
                'agl.id_attribute_group = ag.id_attribute_group AND agl.id_lang = :langId'
            )->where($qb->expr()->in('a.id_attribute', ':attributeIds'))
            ->setParameter('attributeIds', $attributeIds, Connection::PARAM_INT_ARRAY)
            ->setParameter('langId', $langId)
        ;

        $attributesInfo = $qb->execute()->fetchAll();

        $attributesInfoByAttributeId = [];
        foreach ($attributesInfo as $attributeInfo) {
            $attributesInfoByAttributeId[(int) $attributeInfo['id_attribute']] = $attributeInfo;
        }

        return $attributesInfoByAttributeId;
    }

    /**
     * @param ProductId $productId
     * @param array<string, mixed> $filters
     *
     * @return QueryBuilder
     */
    private function getCombinationsQueryBuilder(ProductId $productId, array $filters): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->from($this->dbPrefix . 'product_attribute', 'pa')
            ->where('pa.id_product = :productId')
            ->setParameter('productId', $productId->getValue())
        ;

        // filter by attributes
        if (isset($filters['attribute_ids'])) {
            $combinationIds = $this->getCombinationIdsByAttributeIds((array) $filters['attribute_ids']);
            $qb->andWhere($qb->expr()->in('pa.id_product_attribute', ':combinationIds'))
                ->setParameter('combinationIds', $combinationIds, Connection::PARAM_INT_ARRAY)
            ;
        }

        $qb->orderBy('id_product_attribute', 'asc');

        return $qb;
    }

    /**
     * @param int[] $attributeIds
     *
     * @return int[]
     */
    private function getCombinationIdsByAttributeIds(array $attributeIds): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('pac.id_product_attribute')
            ->from($this->dbPrefix . 'product_attribute_combination', 'pac')
            ->where($qb->expr()->in('pac.id_attribute', ':attributeIds'))
            ->setParameter('attributeIds', $attributeIds, Connection::PARAM_INT_ARRAY)
        ;

        $results = $qb->execute()->fetchAll();

        if (!$results) {
            return [];
        }

        return array_map(function (array $result): int {
            return (int) $result['id_product_attribute'];
        }, $results);
    }
}
