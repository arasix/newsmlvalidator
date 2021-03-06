<?php
require "DocumentDetector.php";
require "DocumentProperties.php";
require "NewsMLValidationResult.php";
require "MicrodataValidationResult.php";
require "NewsMLValidationRunner.php";
require "HTMLValidationRunner.php";
require "MicrodataValidationRunner.php";
require "NITFValidationRunner.php";
require "CurlService.php";
require "XMLSerializer.php";

/**
 * Class NewsMLValidator
 *
 *  Validates NewsML-G2 with XHTML5 + Microdata in three steps.
 *
 */
class NewsMLValidator
{
    public static $supportedStandards = array('NewsML', 'HTML', 'Microdata', 'NITF');


    /**
     * @param string $newsML NewsML-G2 document
     * @param string|null $validationRequest
     * @return array
     */
    public function run($newsML, $standards)
    {
        $validations = array();

        // validate NewsML
        if (in_array('NewsML', $standards)) {
            $validationRunner = new NewsMLValidationRunner();
            $validations[] = $validationRunner->run($newsML);
        }

        // extract contained NewsItems
        $newsItems = $this->extractNewsItems($newsML);

        // validate HTML
        if (in_array('HTML', $standards)) {
            foreach ($newsItems as $newsItem) {
                $guid = $newsItem->getAttribute('guid');
                $validationRunner = new HTMLValidationRunner();
                $validations[] = $validationRunner->run($newsItem, $guid);
            }
        }

        // validate Microdata
        if (in_array('Microdata', $standards)) {
            foreach ($newsItems as $newsItem) {
                $guid = $newsItem->getAttribute('guid');
                $validationRunner = new MicrodataValidationRunner();
                $validations[] = $validationRunner->run($newsItem, $guid);
            }
        }

        // validate NITF
        if (in_array('NITF', $standards)) {
            foreach ($newsItems as $newsItem) {
                $guid = $newsItem->getAttribute('guid');
                $validationRunner = new NITFValidationRunner();
                $validations[] = $validationRunner->run($newsItem, $guid);
            }
        }

        return $validations;
    }

    public static function getNewsMLXpath(DOMDocument $dom)
    {
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('n', 'http://iptc.org/std/nar/2006-10-01/');
        $xp->registerNamespace('h', 'http://www.w3.org/1999/xhtml');
        $xp->registerNamespace('nitf', 'http://iptc.org/std/NITF/2006-10-18/');
        return $xp;
    }

    private function extractNewsItems($newsML)
    {
        $dom = DocumentDetector::loadNewsMLDom($newsML);
        return self::getNewsMLXpath($dom)->query('//n:newsItem');
    }


    public static function getStandardsFromHTTPRequestParameter($param)
    {
        if (empty($param)) {
            return self::$supportedStandards;
        }
        $split = explode(',', $param);
        $standards = array();
        foreach ($split as $standard) {
            if (in_array(trim($standard), self::$supportedStandards)) {
                $standards[] = trim($standard);
            }
        }
        return $standards;
    }

    public static function getRequestedFormat()
    {
        $requestHeaders = getallheaders();
        $format = 'application/json';
        $type = false;
        if (array_key_exists('Accept', $requestHeaders)) {
            $type = $requestHeaders['Accept'];
        } elseif (array_key_exists('accept', $requestHeaders)) {
            $type = $requestHeaders['Accept'];
        }
        if ($type) {
            if (mb_stripos($type, 'xml') !== false) {
                $format = 'text/xml';
            }
        }
        return $format;
    }
}
