<?php
namespace Neos\Neos\Ui\TYPO3CR\Service;

use Neos\Flow\Annotations as Flow;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Utility\NodePaths;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Domain\Model\Domain;
use Neos\Neos\Domain\Repository\DomainRepository;

/**
 * @Flow\Scope("singleton")
 */
class NodeService
{
    /**
     * @Flow\Inject
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * @Flow\Inject
     * @var SiteRepository
     */
    protected $siteRepository;

    /**
     * @Flow\Inject
     * @var DomainRepository
     */
    protected $domainRepository;

    /**
     * Helper method to retrieve the closest document for a node
     *
     * @param NodeInterface $node
     * @return NodeInterface
     */
    public function getClosestDocument(NodeInterface $node)
    {
        if ($node->getNodeType()->isOfType('Neos.Neos:Document')) {
            return $node;
        }

        $flowQuery = new FlowQuery(array($node));
        return $flowQuery->closest('[instanceof Neos.Neos:Document]')->get(0);
    }

    /**
     * Helper method to check if a given node is a document node.
     *
     * @param  NodeInterface $node The node to check
     * @return boolean             A boolean which indicates if the given node is a document node.
     */
    public function isDocument(NodeInterface $node) {
        return ($this->getClosestDocument($node) === $node);
    }

    /**
     * Converts a given context path to a node object
     *
     * @param string $contextPath
     * @return NodeInterface
     */
    public function getNodeFromContextPath($contextPath, Site $site = null, Domain $domain = null)
    {
        $nodePathAndContext = NodePaths::explodeContextPath($contextPath);
        $nodePath = $nodePathAndContext['nodePath'];
        $workspaceName = $nodePathAndContext['workspaceName'];
        $dimensions = $nodePathAndContext['dimensions'];

        $contextProperties = $this->prepareContextProperties($workspaceName, $dimensions);

        if ($site === null) {
            list(,,$siteNodeName) = explode('/', $nodePath);
            $site = $this->siteRepository->findOneByNodeName($siteNodeName);
        }

        if ($domain === null) {
            $domain = $this->domainRepository->findOneBySite($site);
        }

        $contextProperties['currentSite'] = $site;
        $contextProperties['currentDomain'] = $domain;

        $context = $this->contextFactory->create(
            $contextProperties
        );

        $workspace = $context->getWorkspace(false);
        if (!$workspace) {
            return new \Neos\Error\Messages\Error(
                sprintf('Could not convert the given source to Node object because the workspace "%s" as specified in the context node path does not exist.', $workspaceName), 1451392329);
        }

        return $context->getNode($nodePath);
    }

    /**
     * Prepares the context properties for the nodes based on the given workspace and dimensions
     *
     * @param string $workspaceName
     * @param array $dimensions
     * @return array
     */
    protected function prepareContextProperties($workspaceName, array $dimensions = null)
    {
        $contextProperties = array(
            'workspaceName' => $workspaceName,
            'invisibleContentShown' => false,
            'removedContentShown' => false
        );

        if ($workspaceName !== 'live') {
            $contextProperties['invisibleContentShown'] = true;
        }

        if ($dimensions !== null) {
            $contextProperties['dimensions'] = $dimensions;
        }

        return $contextProperties;
    }
}
