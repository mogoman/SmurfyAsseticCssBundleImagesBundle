<?php
/*
 * This file is part of the SmurfyAsseticCssBundleImagesBundle package.
 *
 * (c) smurfy <https://github.com/smurfy>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Smurfy\AsseticCssBundleImagesBundle\Assetic\Filter;

use Assetic\Asset\AssetInterface;
use Assetic\Filter\BaseCssFilter;
use Assetic\Asset\FileAsset;
use Assetic\AssetManager;
use Assetic\AssetWriter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Assetic\Factory\AssetFactory;
use Assetic\Factory\Resource\FileResource;
use Symfony\Component\Routing\Router;

/**
 * The Filter itself
 */
class CssBundleImagesFilter extends BaseCssFilter
{
    private $kernel;
    private $options;
    private $filters;
    private $af;
    private $baseUrl;
    
    /**
     * Constructor.
     *
     * @param KernelInterface $kernel   The kernel is used to parse bundle notation
     * @param AssetFactory    $af       Assetic Factory
     * @param Router          $router   The Router
     * @param array           $options  Options for this filter
     * @param array           $filters  Additional filters for embeded images
     * 
     * @return void
     */
    public function __construct(KernelInterface $kernel, $af, Router $router, $options = array(), $filters = array())
    {
        $this->kernel = $kernel;
        $this->options = $options;
        $this->filters = $filters;
        $this->af = $af;
        $this->baseUrl = $router->getContext()->getBaseUrl();;
    }
    
    /**
     * Overwritten sleep method, because assetics cache feature serializes this class and the embedded
     * assetFactory does not like it.
     * 
     * @return array
     */
    public function __sleep()
    {
        return array();
    }
    
    /**
     * Not in use
     * 
     * @param AssetInterface $asset The asset
     * 
     * @return void
     */
    public function filterLoad(AssetInterface $asset)
    {
    }

    /**
     * Main logic is located here.
     * We parse the css here and create for all matching images a seperate asset
     * 
     * @param AssetInterface $asset The asset
     * 
     * @return void
     */
    public function filterDump(AssetInterface $asset)
    {
        $kernel = $this->kernel;
        $baseUrl = $this->baseUrl;
        $af = $this->af;
        $options = $this->options;
        $filters = $this->filters;
        
        $content = $this->filterUrls($asset->getContent(), function($matches) use($kernel, $af, $baseUrl, $options, $filters)
        {
            $url = $matches['url'];
            if ('@' == $url[0] && false !== strpos($url, '/')) {
                $bundle = substr($url, 1);
                if (false !== $pos = strpos($bundle, '/')) {
                    $bundle = substr($bundle, 0, $pos);
                }
                
                try {
                    $file = $kernel->locateResource($url);
                    $ext = pathinfo($file, PATHINFO_EXTENSION);
                    $assetFilters = array();
                    if (isset($filters[$ext])) {
                        $assetFilters = $filters[$ext];
                    }
                    $id = $af->generateAssetName($file, $assetFilters, $options);
                    $path = str_replace('*', $id, $options['output']) . '.' . $ext;
                    $url = $baseUrl . ($options['absolute'] ? '/' : '') . $path;
                } catch (\Exception $e) {
                    if ($options['debug']) {
                        $subUrl = substr($url, strlen($bundle) + 11);
                        $url = sprintf('/* Resource %s not found in %s */', $subUrl, $bundle);
                    } else {
                        $url = '/* missing */';
                    }
                }
                
            }
            return str_replace($matches['url'], $url, $matches[0]);
        });
        $asset->setContent($content);
    }
}
