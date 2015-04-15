<?php

namespace Xi\Bundle\SearchBundle\Service\Search;

use Symfony\Component\DependencyInjection\Container,
    Xi\Bundle\SearchBundle\Service\Search\Result\DefaultSearchResultSet,
    Xi\Bundle\SearchBundle\Service\Search\Result\DefaultSearchResult,
    Elastica\ResultSet as ElasticaResultSet,
    Elastica\Result as ElasticaResult,
    Elastica\Query as ElasticaQuery,
    Knp\Component\Pager\Pagination,
    FOS\ElasticaBundle\Paginator\TransformedPaginatorAdapter,
    FOS\ElasticaBundle\Subscriber\PaginateElasticaQuerySubscriber,
    Xi\Bundle\SearchBundle\Event\Subscriber\ElasticaQuerySubscriber;

class FOSElasticaSearch implements Search
{
    
    /**
     * @var Container
     */
    private $container;
    
    /*
     * @param FormFactory $formFactory
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container  = $container;
    } 
   
    /**
     * Search returns array of search results
     * 
     * @param string $index  - name of the indexed resource
     * @param string $term   - search term
     * @param int    $limit
     * @return SearchResultSet
     */    
    public function search($index, $term, $limit = null)
    {
        $elasticaType = $this->getSearchable($index);
        $elasticaResultSet = $elasticaType->search($term, $limit);

        return $this->convertToSearchResult($elasticaResultSet);
    }

    /**
     * Gets a paginator wrapping the result of a search
     *
     * @param  string $index
     * @param  string $term
     * @param  int    $page
     * @param  int    $limit
     * @return PaginationInterface
     */
    public function searchPaginated($index, $term, $page = 1, $limit = null)
    {
        $paginator = $this->container->get('knp_paginator');
        $paginator->subscribe(new ElasticaQuerySubscriber());
        $query = ElasticaQuery::create($term);

        $paginationView = $paginator->paginate(array($this->getSearchable($index), $query), $page, $limit);

        $searcResultSet = $this->convertToSearchResult($paginationView->getItems());
        $paginationView->setItems($searcResultSet->getResults());

        return $paginationView;
    }

    /**
     * @param Elastica\ResultSet $elasticaResultSet
     * @return \Xi\Bundle\SearchBundle\Service\Search\Result\DefaultSearchResultSet 
     */
    protected function convertToSearchResult(ElasticaResultSet $elasticaResultSet)
    {
        $results = array();
        foreach($elasticaResultSet as $elasticaResult) {
            $results[] = new DefaultSearchResult(
                $elasticaResult->getIndex(),
                $elasticaResult->getType(),
                $elasticaResult->getId(),
                $elasticaResult->getScore(),
                $elasticaResult->getSource()
            );
        }

        // disable call to getTotalTime until that functions exists in an ruflin/elastica version supported by elastica-bundle
        // return new DefaultSearchResultSet($results, $elasticaResultSet->getTotalHits(), $elasticaResultSet->getTotalTime());
        return new DefaultSearchResultSet($results, $elasticaResultSet->getTotalHits(), 0);
    }

    /**
     * Find and returns array of searched entities
     *
     * @param string $index  - name of the indexed resource
     * @param string $term   - search term
     * @param int    $limit
     * @return  array - array of entities
     */
    public function find($index, $term, $limit = null)
    {
        $mapFinder = $this->getFinder($index);

        return $mapFinder->find($term, $limit);
    }

    /**
     * Gets a paginator wrapping the result of a search
     *
     * @param  string $index
     * @param  string $term
     * @param  int    $page
     * @param  int    $limit
     * @return PaginationInterface
     */
    public function findPaginated($index, $term, $page = 1, $limit = null)
    {
        $paginator = $this->container->get('knp_paginator');
        $paginator->subscribe(new PaginateElasticaQuerySubscriber());

        return $paginator->paginate($this->createPaginatorAdapter($term, $index), $page, $limit);
    }

    /**
     * @param  string $query
     * @param  string $index
     * @return TransformedPaginatorAdapter
     */
    public function createPaginatorAdapter($query, $index)
    {
        $query = ElasticaQuery::create($query);

        return new TransformedPaginatorAdapter(
            $this->getSearchable($index),
            $query,
            array(),
            $this->getTransformer($index)
        );
    }

    /**
     * @param  string $index
     * @return Elastica\Searchable
     */
    protected function getSearchable($index)
    {
        return $this->container->get('fos_elastica.index.' . $index);
    }

    /**
     * @param  string $index
     * @return TransformedFinder
     */
    protected function getFinder($index)
    {
        return $this->container->get('fos_elastica.finder.' . $index);
    }

    /**
     * @param  string $index
     * @return ElasticaToModelTransformer
     */
    protected function getTransformer($index)
    {
        return $this->container->get('fos_elastica.elastica_to_model_transformer.collection.' . $index);
    }
}