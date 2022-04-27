<?php

declare(strict_types=1);

namespace Akeneo\Category\Infrastructure\Component\CategoryTree\UseCase;

use Akeneo\Category\Infrastructure\Component\CategoryTree\Query;
use Akeneo\Category\Infrastructure\Component\CategoryTree\ReadModel;
use Akeneo\Tool\Component\Classification\Repository\CategoryRepositoryInterface;
use Akeneo\UserManagement\Bundle\Context\UserContext;

/**
 * @copyright 2018 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ListRootCategoriesWithCountHandler
{
    /** @var CategoryRepositoryInterface */
    private $categoryRepository;

    /** @var UserContext */
    private $userContext;

    /** @var Query\ListRootCategoriesWithCountIncludingSubCategories */
    private $listAndCountIncludingSubCategories;

    /** @var Query\ListRootCategoriesWithCountNotIncludingSubCategories */
    private $listAndCountNotIncludingSubCategories;

    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        UserContext $userContext,
        Query\ListRootCategoriesWithCountIncludingSubCategories $listAndCountIncludingSubCategories,
        Query\ListRootCategoriesWithCountNotIncludingSubCategories $listAndCountNotIncludingSubCategories
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->userContext = $userContext;
        $this->listAndCountIncludingSubCategories = $listAndCountIncludingSubCategories;
        $this->listAndCountNotIncludingSubCategories = $listAndCountNotIncludingSubCategories;
    }

    /**
     * @return ReadModel\RootCategory[]
     */
    public function handle(ListRootCategoriesWithCount $query): array
    {
        $categorySelectedAsFilter = -1 !== $query->categoryIdSelectedAsFilter() ?
            $this->categoryRepository->find($query->categoryIdSelectedAsFilter()) : null;

        if (null === $categorySelectedAsFilter) {
            $categorySelectedAsFilter = $this->userContext->getAccessibleUserTree();
            if (null === $categorySelectedAsFilter) {
                return [];
            }
        }
        $rootCategoryIdToExpand = $categorySelectedAsFilter->getRoot();

        $rootCategories = $query->countIncludingSubCategories() ?
            $this->listAndCountIncludingSubCategories->list(
                $query->translationLocaleCode(),
                $query->userId(),
                $rootCategoryIdToExpand
            ) :
            $this->listAndCountNotIncludingSubCategories->list(
                $query->translationLocaleCode(),
                $query->userId(),
                $rootCategoryIdToExpand
            );

        return $rootCategories;
    }
}
