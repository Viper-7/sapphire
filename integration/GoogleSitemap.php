<?php

/**
 * Initial implementation of Sitemap support.
 * GoogleSitemap should handle requests to 'sitemap.xml'
 * the other two classes are used to render the sitemap
 * @package sapphire
 * @subpackage misc
 */
class GoogleSitemap extends Controller {
	
	/**
	 * @var boolean
	 */
	protected static $enabled = true;
	
	/**
	 * @var DataObjectSet
	 */
	protected $Pages;
	
	/**
	 * @var boolean
	 */
	protected static $pings = true;
	
	public function Items() {
		$this->Pages = Versioned::get_by_stage('SiteTree', 'Live');

		$newPages = new DataObjectSet();
		
		foreach($this->Pages as $page) {
			// Only include pages from this host and pages which are not an instance of ErrorPage 
			if(parse_url($page->AbsoluteLink(), PHP_URL_HOST) == $_SERVER['HTTP_HOST'] && !($page instanceof ErrorPage)) {
			
				// If the page has been set to 0 priority, we set a flag so it won't be included
				if(!isset($page->Priority) || $page->Priority > 0) {
					// The one field that isn't easy to deal with in the template is
					// Change frequency, so we set that here.
					$properties = $page->toMap();
					$created = new SSDatetime($properties['Created']);
					$now = new SSDatetime();
					$versions = $properties['Version'];
					$timediff = $now->format('U') - $created->format('U');
			
					// Check how many revisions have been made over the lifetime of the
					// Page for a rough estimate of it's changing frequency.
			
					$period = $timediff / ($versions + 1);
			
					if($period > 60*60*24*365) { // > 1 year
						$page->ChangeFreq='yearly';
					} else if($period > 60*60*24*30) { // > ~1 month
						$page->ChangeFreq='monthly';
					} else if($period > 60*60*24*7) { // > 1 week
						$page->ChangeFreq='weekly';
					} else if($period > 60*60*24) { // > 1 day
						$page->ChangeFreq='daily';
					} else if($period > 60*60) { // > 1 hour
						$page->ChangeFreq='hourly';
					} else { // < 1 hour
						$page->ChangeFreq='always';
					}
				
					$newPages->push($page);
				}
			}
		}
		return $newPages;
	}
	
	/**
	 * @return string Response text
	 */
	static function ping() {
		if(!self::$enabled) return false;
		
		//Don't ping if the site has disabled it, or if the site is in dev mode
		if(!GoogleSitemap::$pings || Director::isDev())
			return;
			
		$location = urlencode(Director::absoluteBaseURL() . '/sitemap.xml');
		
		$response = HTTP::sendRequest("www.google.com", "/webmasters/sitemaps/ping",
			"sitemap=" . $location);
			
		return $response;
	}
	
	public static function enable_google_notification() {
		self::$pings = true;
	}
	
	/**
	 * Disables pings to google when the sitemap changes.
	 */
	public static function disable_google_notification() {
		self::$pings = false;
	}
	
	function index($url) {
		if(self::$enabled) {
			// We need to override the default content-type
			ContentNegotiator::disable();
			header('Content-type: application/xml; charset="utf-8"');

			// But we want to still render.
			return array();
		} else {
			return new HTTPResponse('Not allowed', 405);
		}

	}
	
	public static function enable() {
		self::$enabled = true;
	}
	
	public static function disable() {
		self::$enabled = false;
	}
}
?>