<?php
/*
 *
 */	
namespace FishPig\WordPress\Model;

/* Parent Class */
use FishPig\WordPress\Model\AbstractModel;

/* Interface */
use FishPig\WordPress\Api\Data\Entity\ViewableInterface;

/* Misc */
use FishPig\WordPress\Model\PostTypeManager;

class Taxonomy extends AbstractModel/* implements ViewableInterface*/
{	
	/**
	 * Get the URI's that apply to $uri
	 *
	 * @param string $uri = ''
	 * @return array|false
	 */
	public function getUris($uri = '')
	{
		if ($uri && $this->getSlug() && strpos($uri, $this->getSlug()) === false) {
			return false;
		}

		return $this->getAllUris();
	}
	
	/**
	 * Get all of the URI's for this taxonomy
	 *
	 * @return array|false
	 */
	public function getAllUris()
	{
		if ($this->hasAllUris()) {
			return $this->_getData('all_uris');
		}
		
		$this->setAllUris(false);

		$resource   = $this->wpContext->resourceConnection;
		$connection = $resource->getConnection();
		
		$select = $connection->select()
			->from(
				array(
					'term' => $resource->getTable('wordpress_term')), 
					array(
						'id' => 'term_id', 
						'url_key' => 'slug',
						new \Zend_Db_Expr("TRIM(LEADING '/' FROM CONCAT('" . rtrim($this->getSlug(), '/') . "/', slug))")
					)
				)
				->join(
					array('tax' => $resource->getTable('wordpress_term_taxonomy')),
					$connection->quoteInto("tax.term_id = term.term_id AND tax.taxonomy = ?", $this->getTaxonomyType()),
					'parent'
				);

		if ($results = $connection->fetchAll($select)) {
			$this->setAllUris(PostType::generateRoutesFromArray($results, $this->getSlug()));
		}

		return $this->_getData('all_uris');
	}

	/**
	 * Retrieve the URI for $term
	 *
	 * @param \FishPig\WordPress\Model\Term $term
	 * @return false|string
	 */
	public function getUriById($id, $includePrefix = true)
	{
		if (($uris = $this->getAllUris()) !== false) {
			if (isset($uris[$id])) {
				$uri = $uris[$id];

				if (!$includePrefix && $this->getSlug() && strpos($uri, $this->getSlug() . '/') === 0) {
					$uri = substr($uri, strlen($this->getSlug())+1);
				}
				
				return $uri;
			}
		}

		return false;
	}

	/**
	 * Determine whether the taxonomy uses a hierarchy in it's link
	 *
	 * @return  bool
	 */
	public function isHierarchical()
	{
		return (int)$this->getData('hierarchical') === 1;
	}
	
	/**
	 * Get the taxonomy slug
	 *
	 * @return string
	 */
	public function getSlug()
	{
		return trim($this->getData('rewrite/slug'), '/');
	}
	
	/**
	 * Change the 'slug' value
	 *
	 * @param string $slug
	 * @return $this
	**/
	public function setSlug($slug)
	{
		if (!isset($this->_data['rewrite'])) {
			$this->_data['rewrite'] = array();
		}
		
		$this->_data['rewrite']['slug'] = $slug;
		
		return $this;
	}
	
	/**
	 * Get a collection of terms that belong this taxonomy and $post
	 *
	 * @param \FishPig\WordPress\Model\Post $post
	 * @return \FishPig\WordPress\Model\ResourceModel\Post\Collection
	 */
	public function getPostTermsCollection(\FishPig\WordPress\Model\Post $post)
	{
		return $this->termFactory->create()->getCollection()
			->addTaxonomyFilter($this->getTaxonomyType())
			->addPostIdFilter($post->getId());
	}
	
	public function getTaxonomyType()
	{
		return $this->getData('taxonomy_type') ? $this->getData('taxonomy_type') : $this->getData('name');
	}
	
	public function getTaxonomy()
	{
		return $this->getTaxonomyType();
	}
}