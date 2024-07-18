<?php
/**
 * zendeskfd plugin for Craft CMS 3.x
 *
 * Forward form submissions to zendesk
 *
 * @link      https://www.mitcs.com
 * @copyright Copyright (c) 2022 MatsonIsomTech
 */

namespace mitcs\zendeskfd;


use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\events\ModelEvent;
use craft\elements\Entry;

use craft\services\Elements;

use yii\base\Event;
use yii\db\Query;


/**
 * Craft plugins are very much like little applications in and of themselves. We’ve made
 * it as simple as we can, but the training wheels are off. A little prior knowledge is
 * going to be required to write a plugin.
 *
 * For the purposes of the plugin docs, we’re going to assume that you know PHP and SQL,
 * as well as some semi-advanced concepts like object-oriented programming and PHP namespaces.
 *
 * https://docs.craftcms.com/v3/extend/
 *
 * @author    MatsonIsomTech
 * @package   Zendeskfd
 * @since     1.0.0
 *
 */
class Zendeskfd extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * Zendeskfd::$plugin
     *
     * @var Zendeskfd
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * To execute your plugin’s migrations, you’ll need to increase its schema version.
     *
     * @var string
     */
    public $schemaVersion = '1.0.0';

    /**
     * Set to `true` if the plugin should have a settings view in the control panel.
     *
     * @var bool
     */
    public $hasCpSettings = false;

    /**
     * Set to `true` if the plugin should have its own section (main nav item) in the control panel.
     *
     * @var bool
     */
    public $hasCpSection = false;


    public static $_fileTarget;
    // Public Methods
    // =========================================================================

    /**
     * Set our $plugin static property to this class so that it can be accessed via
     * Zendeskfd::$plugin
     *
     * Called after the plugin class is instantiated; do any one-time initialization
     * here such as hooks and events.
     *
     * If you have a '/vendor/autoload.php' file, it will be loaded for you automatically;
     * you do not need to load it in your init() method.
     *
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        // Do something after we're installed
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                    // We were just installed
//                    $fh = fopen('hmm', 'a+');
//                    fwrite($fh, 'AAAA');
//                    fclose($fh);

                }
            }
        );

        Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT, function(Event $event) {

            //check if it's a sprout form (-- this is obviously brittle)
            if (isset($event->element->formId)) {
				
				//Zendeskfd::log('form id: ' . $event->element->formId);
				
				if($event->element->formId != 372){ //only run for the contact form.. may be a better way?
					return;
				}
				
                $element_id = $event->element->id;

                /* JOIN EXAMPLE:
                 * $result=    (new Query())
                    ->select('table1.col1, table1.col2,,,, table2.co1, table2.col2 ...')
                    ->from('products ')
                    ->innerJoin('movements', 'product.key= movements.key');
                    */
                //$fh = fopen('form_save_log', 'a+');
		
		//using a new logging method: https://craftcms.stackexchange.com/questions/25427/craft-3-plugins-logging-in-a-separate-log-file
	       //Zendeskfd::log('testing a log message');	

                // key = 'field_' + <key> in sprout forms table
                // value = zendesk field ID
                $sprout_forms_to_zendesk_map = [
                  'category' => '5742520641051',
                ];

                //key = sprout forms value
                // value = zendesk field tag name
                $category_answers = [  //it will have to be one of these
                    'submitOrReceiveInformation' => 'information',
                    'sendUsFeedback' => 'comment',
                    'logAComplaint' => 'complaint',
                    'onlineOrders' => 'online_orders',
                    'venueRequests' => 'venue_services',
                    'marketingRequests' => 'marketing_requests',
                ];

                $query1 = new Query();
                //$related = $query1->select(['relations.id', 'relations.targetId', 'assets.filename'])
				
				//Zendeskfd::log('element_id: ' . $element_id);
				
                $related = $query1->select(['relations.id as rel_id', 'relations.targetId', 'elements.id as el_id', 'assets.filename'])
                    ->from('relations')
                    ->join('LEFT JOIN', 'elements', 'elements.id = relations.targetId')
                    ->join('LEFT JOIN', 'assets', 'assets.id=elements.id')
                    ->where(['sourceId' => $element_id])
                    // ->andWhere('elements.type = \'craft\\elements\\Asset\'')  // not interested in getting this to work.. only things related to forms should
					// be images, yeah?
                    ->all();
					
				//$raw_sql = $query1->getRawSql(); // can't get this to work
				//Zendeskfd::log('related image query: ' . $raw_sql);
					
		$related_log = print_r($related, 1);
		
		
		
		Zendeskfd::log('related image query result: ' . $related_log);

                $attached_files = [];

                $upload_folder = '../private/assets/contact/';  // -- still not sure where to find this folder location in the database ... needs searching 8/15/2022

                $curl_upload_command = <<<EOF
curl "https://lundbergsupport.zendesk.com/api/v2/uploads.json?filename=%filename%" \
  --data-binary "@%full_path%" \
  -H "Content-Type: application/binary" \
  -u info@lundberg.com:Lundberg1? \
  -X POST
EOF;

                $upload_tokens = [];

                foreach ($related as $k => $row) { // get a token from zendesk API
					
					if(!strlen(trim($row['filename']))){
						continue;
					}
					
                    Zendeskfd::log('sending image to zendesk, image: ' . $row['filename']);

                    //$attached_files[] = $row['filename'];
                    $curl_command = str_replace('%filename%', $row['filename'], $curl_upload_command);
                    $curl_command = str_replace('%full_path%', $upload_folder . $row['filename'], $curl_command);
					
					Zendeskfd::log('zendesk image curl command: ' . $curl_command);

					
                    $api_response = shell_exec($curl_command);

					Zendeskfd::log('zendesk image upload response: ' . $api_response);

                    $api_response_json = json_decode($api_response);

                    $upload_tokens[] = $api_response_json->upload->token;


                }

                //$related_object = print_r($related, true);
                //fwrite($fh, "related = \n" . $related_object . "\n\n");

                //$api_response_object = print_r($api_response, true);
                //fwrite($fh, "api_response = \n" . $api_response_object . "\n\n");

                //$api_response_json_object = print_r($api_response_json, true);
                //fwrite($fh, "api_response_json = \n" . $api_response_json_object . "\n\n");

                //$upload_tokens_object = print_r($upload_tokens, true);
                //fwrite($fh, "upload_tokens = \n" . $upload_tokens_object . "\n\n");

                //die('keep trying');

                $query2 = new Query();
                $info = $query2->select(
                    [
                        'field_name1',
                        'field_email1',
                        'field_paragraph1',
                        'field_phone1',
                        'field_category',
//                        'field_information_sub_category',
//                        'field_feedback_sub_category',
//                        'field_complaint_sub_category',
                        'field_bestBefore',
                        'field_singleLine1',
                        //'field_purchasePrice', //remove from form
                        'field_numberOfItems',
                        'field_storeName',
                        //'field_storeLocation', //remove from form
                        'field_paragraph2',
                        'field_paragraph3'
                    ])
                    ->from('sproutformscontent_contact')
                    ->where(['elementId' => $element_id])
                    ->limit(1)
                    ->one();


                //assign subcategory
                //$subcategory=0;
                $no_subcategory = 1;
				
				/*
                switch($info['field_category']){

                    case 'sendUsFeedback':
                        //$subcategory = $info['field_feedback_sub_category'];
                        //feedbackPraise, feedbackOther
                        $no_subcategory = 1;
                        break;
                    case 'submitOrReceiveInformation':
                        //$subcategory = $info['field_information_sub_category'];
                        $no_subcategory = 1;
                        break;
                    case 'logAComplaint':
                        //$subcategory = $info['field_complaint_sub_category'];
                        $no_subcategory = 1;
                        break;
                    case 'venueRequests':
                        $subcategory = 'services_and_requests';
                        break;
                    case 'marketingRequests':
                        $subcategory = 'marketing_requests';
                        break;
                    case 'onlineOrders':
                        $subcategory = 'direct_to_consumer';
                        break;
                    default:
                        break;
                }
				*/
				
                // leaving off -- 2022/08/26 -- now
                // that there is only one category, and no subcategories
                // to the form, those columns in the database
                // no longer exist... so. fix this.
				/*
                $subcategory_zendesk_map = [
                    0 => '', //default
                    'feedbackOther' => 'feedback',
                    'feedbackPraise' => 'praise',
                    'allergens' => 'praise',
                    'arsenicInFood' => 'arsenic',
                    'foodSafety' => 'food_safety',
                    'generalInquiry' => 'general_inquiry',
                    'marketingRequests' => 'marketing_requests',
                    'onlineOrders' => 'online_orders',
                    'sustainability' => 'sustainability',
                    'venueServices' => 'venue_services',
                    'whereToBuyInCanada' => 'canada_locations',
                    'foreignMaterial' => 'foreign_material',
                    'illnessInjury' => 'illness_and_injury',
                    'insectPest' => 'insect_and_pest',
                    'mislabeled' => 'mislabeled',
                    'missingComponents' => 'missing_components',
                    'packaging' => 'packaging',
                    'preparation' => 'preparation_complaint',
                    'shelfLife' => 'shelf_life',
                    'spoilage' => 'spoilage',
                    'unacceptableCharacteristics' => 'unacceptable_characteristics',
                    'weightVolume' => 'weight_and_volume',
                ];
				*/
				
				
                //$event_object = print_r($info, true);
                //fwrite($fh, $event_object . "\n\n");

                $query3 = new Query();
                $address = $query3->select(
                    [
                        'countryCode',
                        'administrativeAreaCode',
                        'locality',
                        'postalCode',
                        'address1',
                        'address2'
                    ])
                    ->from('sprout_addresses')
                    ->where(['elementId' => $element_id])
                    ->limit(1)
                    ->one();

                $field_name1 = json_decode($info['field_name1']);

                $full_name = $field_name1->prefix . ' ' .
                    $field_name1->firstName . ' ' .
                    (!empty($field_name1->middleName) ? $field_name1->middleName . ' ' : '') . //don't add whitespace
                    $field_name1->lastName . ' ' .
                    $field_name1->suffix;

                $field_phone1 = json_decode($info['field_phone1']);

                $phone = isset($field_phone1->phone)?$field_phone1->phone:'';

                //combine the multiple paragraph* fields into one comment
                $comment = strlen(trim($info['field_paragraph1'])) ? $info['field_paragraph1'] : '';
                $comment .= strlen(trim($info['field_paragraph2'])) ? $info['field_paragraph2'] : '';
                $comment .= strlen(trim($info['field_paragraph3'])) ? $info['field_paragraph3'] : '';

                // the zendesk API need *something* in the comment field, in case it was left blank
                if(0 === strlen(trim($comment))){
                    $comment .= '[no comment]';
                }
		
		$address_comment = '';
		
		if($address){
                    $address_comment = '' . $address['address1'] . ' ' . $address['address2'];
                    $address_comment .= "===" . $address['locality'] . ', ' . $address['administrativeAreaCode'] . ' ' . $address['postalCode'];
                    $address_comment .= "===" . $address['countryCode'];
		}
                //$field_bestBefore = $info['field_bestBefore']; // have to do some formatting to match zendesk regex for this field
                $field_bestBeforeTime = strtotime($info['field_bestBefore']);
                $best_before_time_zendesk = date('ymd', $field_bestBeforeTime);

                /*
                $other_fields = [
                    'field_category',  // case type  5742520641051, tags: complaint, comment, information
                    'field_information_sub_category',  //category 5911189288987, tags -- these are all the same on zendesk
                    'field_feedback_sub_category', //category 5911189288987, tags
                    'field_complaint_sub_category', //category 5911189288987, tags
                    'field_bestBefore',  // BBD 6738738486043
                    'field_singleLine1',  // manufacture code 5910445586331
                    //	'field_purchasePrice',  -- remove from form
                    'field_numberOfItems',  // quantity 5910453687067
                    'field_storeName',  // 8021221848475
                    //	'field_storeLocation',  -- remove from form
                    'field_paragraph2',
                    'field_paragraph3'
                ];
                */


                // category 5911189288987, tags: allergens, arsenic, certifications, direct_to_consumer,
                // dislike, feedback, food_safety, foreign_material, illness_and_injury, insect_and_pest,
                // mislabeled, missing_components, nutrition, packaging, praise, preparation_complaint,
                // products, promotions, services_and_requests, shelf_life, spoilage, sustainability,
                // transportaion_and_distribution, unacceptable_characteristics, weight_and_volume,
                // dtc_complaint

                // add to comment
                //foreach ($other_fields as $k => $v) {
                //    $label = str_replace('field_', '', $v);
                //    $comment .= $label . ': ' . $info[$v] . "\n";
                //}

                $comment .= "---" . $phone;
                $comment .= "---" . $address_comment;

                //{"prefix":null,"firstName":"Nicole","middleName":null,"lastName":"Boileau","suffix":null}

                // to get the associated file uploads, use the relations table, finding all the targetId's with
                // the sourceId equal to the $element_id variable above
                // also the type on the joined element table should be 'craft\elements\Asset'
                // BUT -- we need the tokens from the uploads *before* we submit the comment,
                // so grab those first, above

                $include_uploads = '';

                if (count($upload_tokens)) {
                    $include_uploads = ', \"uploads\":[\"' . (implode('\",\"', $upload_tokens)) . '\"]';
                }

                if($no_subcategory) {
                    $curl_command_body = <<<EOF
{\"ticket\": 
	{\"subject\": \"Web Form Submission\", 
		\"comment\": { 
			\"body\": %s%s
		},
		\"requester\": {\"name\":%s, \"email\":%s},
		\"custom_fields\": [
		    {\"id\": 5742520641051, \"value\": %s},
		    {\"id\": 8021221848475, \"value\": %s},		    
		    {\"id\": 5910445586331, \"value\": %s},
		    {\"id\": 6738738486043, \"value\": %s},
		    {\"id\": 5910453687067, \"value\": %s}
		]
	}
}
EOF;
                    $curl_command_body = sprintf($curl_command_body,
                        $this->jsonify($comment),
                        $include_uploads,
                        $this->jsonify(trim($full_name)),
                        $this->jsonify($info['field_email1']),
                        $this->jsonify($category_answers[$info['field_category']]),  //custom field 1
                        $this->jsonify($info['field_storeName']), //custom field 2
                        $this->jsonify($info['field_singleLine1']), //custom field 4
                        $this->jsonify($best_before_time_zendesk), //custom field 5
                        $this->jsonify($info['field_numberOfItems']) //custom field 6
                    );
                } else {
					
					// this should never happen
					/*
                    $curl_command = <<<EOF
curl https://lundbergsupport.zendesk.com/api/v2/tickets.json -d '
{"ticket": 
	{"subject": "Web Form Submission", 
		"comment": { 
			"body": %s%s
		},
		"requester": {"name":%s, "email":%s},
		"custom_fields": [
		    {"id": 5742520641051, "value": %s},
		    {"id": 8021221848475, "value": %s},
		    {"id": 5911189288987, "value": %s},
		    {"id": 5910445586331, "value": %s},
		    {"id": 6738738486043, "value": %s},
		    {"id": 5910453687067, "value": %s}
		]
	}
}' -H "Content-Type: application/json" -v -u info@lundberg.com:Lundberg1? -X POST
EOF;
                    $curl_command = sprintf($curl_command,
                        $this->jsonify($comment),
                        $include_uploads,
                        trim($full_name),
                        $info['field_email1'],
                        $this->jsonify($category_answers[$info['field_category']]),  //custom field 1
                        $this->jsonify($info['field_storeName']), //custom field 2
                        $this->jsonify($subcategory), //custom field 3
                        $this->jsonify($info['field_singleLine1']), //custom field 4
                        $this->jsonify($best_before_time_zendesk), //custom field 5
                        $this->jsonify($info['field_numberOfItems']) //custom field 6
                    );
                    */
                }
// I don't know... should be okay coming out of the database??? we might need some shell-safe filters if we use curl on the command line instead of the curl php module
				
				
	
	      $curl_command = <<<EOF
curl https://lundbergsupport.zendesk.com/api/v2/tickets.json -d "
EOF;

$curl_command_body = str_replace('\\n', "\n", $curl_command_body);
//$curl_command_body = stripslashes($curl_command_body);

$curl_command .= $curl_command_body;

$curl_command .= <<<EOF
" -H "Content-Type: application/json" -v -u info@lundberg.com:Lundberg1? -X POST
EOF;
				
				
				Zendeskfd::log('submitting to zendesk w/ command : ' . $curl_command);
                $shell_output = shell_exec($curl_command);

                //fwrite($fh, 'curl_command = ' . $curl_command . "\n\n");

                $shell_object = print_r($shell_output, true);
                
                Zendeskfd::log('command response : ' . $shell_object);
                
                //fwrite($fh, $shell_object . "\n\n");

                //fclose($fh);
            }
        }
        );
/**
 * Logging in Craft involves using one of the following methods:
 *
 * Craft::trace(): record a message to trace how a piece of code runs. This is mainly for development use.
 * Craft::info(): record a message that conveys some useful information.
 * Craft::warning(): record a warning message that indicates something unexpected has happened.
 * Craft::error(): record a fatal error that should be investigated as soon as possible.
 *
 * Unless `devMode` is on, only Craft::warning() & Craft::error() will log to `craft/storage/logs/web.log`
 *
 * It's recommended that you pass in the magic constant `__METHOD__` as the second parameter, which sets
 * the category to the method (prefixed with the fully qualified class name) where the constant appears.
 *
 * To enable the Yii debug toolbar, go to your user account in the AdminCP and check the
 * [] Show the debug toolbar on the front end & [] Show the debug toolbar on the Control Panel
 *
 * http://www.yiiframework.com/doc-2.0/guide-runtime-logging.html
 */
        Craft::info(
            Craft::t(
                'zendeskfd',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );

    }

    // Protected Methods
    // =========================================================================
    
    function jsonify($val){
		
		//$val = str_replace("\n", '\\\\n', $val);
		
		//$val = str_replace('\'', "'\''", $val); //replace single quotes w/ escaped single quote in single quotes ... hmmm. 
		
        $val = addslashes(json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK));
        
        $val = str_replace("\'", "'", $val);  //remove the slash before single quote
        
        $val = str_replace("---", "\n --- \n", $val);
        
        $val = str_replace("===", " \n ", $val);
        
        $val = str_replace("\n", '\\\\n', $val);
        
        return $val;
        
    }
    
   public static function log($message){
    if(self::$_fileTarget === null){
        /** @var FileTarget _fileTarget */
        self::$_fileTarget = Craft::createObject('\mitcs\zendeskfd\ZendeskfdFiletarget');
        // set the path
        self::$_fileTarget->setLogFile(CRAFT_BASE_PATH.'/storage/logs/');
        // include your target to the current dispatcher targets 
        // -> all messages are tracked in your target as well. 
        Craft::getLogger()->dispatcher->targets[] = self::$_fileTarget;
    }

    // just use the default Craft/Yii logging method but with your category
    Craft::getLogger()->log($message, \yii\log\Logger::LEVEL_INFO, 'zendeskfd');
}

}
