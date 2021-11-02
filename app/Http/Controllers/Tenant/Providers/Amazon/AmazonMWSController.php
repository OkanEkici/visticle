<?php

namespace App\Http\Controllers\Tenant\Providers\Amazon;

use App\Http\Controllers\Controller;

use KeithBrink\AmazonMws\AmazonFeed;

use View;
use Log;
use App\Tenant\Article;

class AmazonMWSController extends Controller
{

    private $config;
    private $feedXML;
    private $feedResponse;

    public function __construct($config = null) {
        if($config) {
            $this->config = $config;
        }
    }

    /*
        XML Feeds
        Documentation XML: https://images-na.ssl-images-amazon.com/images/G/01/rainier/help/XML_Documentation_Intl.pdf
        Documentation Feeds: https://docs.developer.amazonservices.com/en_US/feeds/Feeds_SubmitFeed.html
        XSDs:
        AmazonEnvelope: https://images-na.ssl-images-amazon.com/images/G/01/rainier/help/xsd/release_1_9/amzn-envelope.xsd
        Header: https://images-na.ssl-images-amazon.com/images/G/01/rainier/help/xsd/release_1_9/amzn-header.xsd
        Base: https://images-na.ssl-images-amazon.com/images/G/01/rainier/help/xsd/release_1_9/amzn-base.xsd

    */

    //XSD: https://images-na.ssl-images-amazon.com/images/G/01/rainier/help/xsd/release_1_9/Product.xsd
    public function sendProductFeed($articles) {
        try {
            $feed = $this->createFeed('product', $articles);
            $amz = new AmazonFeed('store1');
            if($this->config != null) {
                $amz->setConfig($this->config);
            }
            //http://docs.developer.amazonservices.com/en_UK/feeds/Feeds_FeedType.html
            $amz->setFeedType('_POST_PRODUCT_DATA_'); 
            $amz->setFeedContent($feed);
            $amz->submitFeed();
            $this->feedResponse = $amz->getResponse();
            Log::info('Amazon MWS: Send Product Feed Success: ', $this->feedResponse);
        } catch (Exception $ex) {
            Log::error('Amazon MWS: Send Product Feed Error: ', $ex);
        }
    }

    //XSD: https://images-na.ssl-images-amazon.com/images/G/01/rainier/help/xsd/release_1_9/Inventory.xsd
    public function sendInventoryFeed($articles, $branchIds, $minQty) {
        try {
            $feed = $this->createFeed('inventory', $articles, $branchIds, $minQty);
            $amz = new AmazonFeed('store1');
            if($this->config != null) {
                $amz->setConfig($this->config);
            }
            //http://docs.developer.amazonservices.com/en_UK/feeds/Feeds_FeedType.html
            $amz->setFeedType('_POST_INVENTORY_AVAILABILITY_DATA_');
            $amz->setFeedContent($feed);
            $amz->submitFeed();
            $this->feedResponse = $amz->getResponse();
            Log::info('Amazon MWS: Send Inventory Feed Success: ', $this->feedResponse);
        } catch (Exception $ex) {
            Log::error('Amazon MWS: Send Inventory Feed Error: ', $ex);
        }
    }

    //XSD: https://images-na.ssl-images-amazon.com/images/G/01/rainier/help/xsd/release_1_9/Price.xsd
    public function sendPricesFeed($articles) {
        try {
            $feed = $this->createFeed('price', $articles);
            $amz = new AmazonFeed('store1');
            if($this->config != null) {
                $amz->setConfig($this->config);
            }
            //http://docs.developer.amazonservices.com/en_UK/feeds/Feeds_FeedType.html
            $amz->setFeedType('_POST_PRODUCT_PRICING_DATA_');
            $amz->setFeedContent($feed);
            $amz->submitFeed();
            $this->feedResponse = $amz->getResponse();
            Log::info('Amazon MWS: Send Price Feed Success: ', $this->feedResponse);
        } catch (Exception $ex) {
            Log::error('Amazon MWS: Send Price Feed Error: ', $ex);
        }
    }

    /**
    * This function will retrieve a list of all items with quantity that was adjusted within the past 24 hours.
    * The entire list of items is returned, with each item contained in an array.
    * Note that this does not relay whether or not the feed had any errors.
    * To get this information, the feed's results must be retrieved.
    */
    public function getAmazonFeedStatus() {
        try {
            $amz = new AmazonFeedList('store1');
            $amz->setTimeLimits('- 24 hours'); //limit time frame for feeds to any updated since the given time
            $amz->setFeedStatuses(array("_SUBMITTED_", "_IN_PROGRESS_", "_DONE_")); //exclude cancelled feeds
            $amz->fetchFeedSubmissions(); //this is what actually sends the request
            return $amz->getFeedList();
        } catch (Exception $ex) {
            Log::error('Amazon MWS: Get Amazon Feed Status Error: ', $ex);
        }
    }

    /**
    * This function will get the processing results of a feed previously sent to Amazon and give the data.
    * In order to do this, a feed ID is required. The response is in XML.
    */
    public function getFeedResult($feedId) {
        try {
            $amz = new AmazonFeedResult('store1', $feedId); //feed ID can be quickly set by passing it to the constructor
            $amz->setFeedId($feedId); //otherwise, it must be set this way
            $amz->fetchFeedResult();
            return $amz->getRawFeed();
        } catch (Exception $ex) {
            Log::error('Amazon MWS: Get Feed Result Error: ', $ex);
        }
    }

    private function createFeed($type, $articles, $branchIds = [], $minQty = 0,$testingLog=false) {

        $xml = '';
        switch($type) {
            case 'product':
                $xml = View::make('tenant.xml.amazonMWS.productFeed')->with(['articles' => $articles])->render();
            break;
            case 'inventory':
                $xml = View::make('tenant.xml.amazonMWS.inventoryFeed')->with(['articles' => $articles, 'branchIds' => $branchIds, 'minQty' => $minQty])->render();
            break;
            case 'price':
                $xml = View::make('tenant.xml.amazonMWS.pricesFeed')->with(['articles' => $articles])->render();
            break;
            default:
            break;
        }
        if($testingLog){Log::info($testingLog);}
        return $xml;
    }
}
