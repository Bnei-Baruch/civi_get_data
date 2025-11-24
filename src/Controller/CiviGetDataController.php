<?php

namespace Drupal\civi_get_data\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Civi\Api4\Contact;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Exception;

class CiviGetDataController extends ControllerBase {

	protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
	}

	public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
	}

	public function mealCalendarStatus(Request $request) {
		$init = $this->initCiviCRM();
		$obj = json_decode($init->getContent());
		if ($obj->status != 'success') {
	    \Drupal::logger('civi_get_data')->error($e->getMessage());
		  return new JsonResponse([
	      'status' => 'error',
	      'message' => 'getContent error',
	      'details' => '',
	    ]);
			return $init;
		}

	  // Get logged-in contact ID
		$contact_id = \CRM_Core_Session::singleton()->getLoggedInContactID();
 		if (!$contact_id) {
	    \Drupal::logger('civi_get_data')->error($e->getMessage());
		  return new JsonResponse([
	      'status' => 'error',
	      'message' => 'getLoggedInContactID error',
	      'details' => '',
	    ]);
		  return new JsonResponse([
				'status' => 'error',
				'message' => 'User not logged in'
			]);
	  }

		$civiDb = Database::getConnection('default', 'civicrm');

		// get activities:
		$sql = "
SELECT cf.id__1439 as meal_id, 1 as card, a.subject, a.activity_date_time, 1
FROM civicrm_activity_contact ac
INNER JOIN civicrm_activity a ON a.id = ac.activity_id
	AND ac.record_type_id = '3'
	AND a.activity_type_id = '178'
	AND a.status_id = '17'
	AND DATE(a.activity_date_time) = CURDATE()
INNER JOIN civicrm_value_registration__243 cf ON cf.entity_id = a.id
WHERE ac.contact_id = :contact_id

UNION

(
SELECT DISTINCT cf.id__1439 as meal_id, 0 as card, a.subject, a.activity_date_time, 2
FROM civicrm_activity_contact ac
INNER JOIN civicrm_activity a ON a.id = ac.activity_id
	AND ac.record_type_id = '3'
	AND a.activity_type_id = '178'
	AND a.status_id = '2'
    AND a.activity_date_time >= NOW() - INTERVAL 60 DAY
INNER JOIN civicrm_value_registration__243 cf ON cf.entity_id = a.id
INNER JOIN civicrm_contribution cc ON cc.id = cf.id_for_the_payment_1375 AND cc.contribution_status_id = 1
WHERE ac.contact_id = :contact_id
)
	";
	  try {
	    $results = $civiDb->query($sql, [ ':contact_id' => $contact_id, ])->fetchAll();
	  } catch (Exception $e) {
	    \Drupal::logger('civi_get_data')->error($e->getMessage());
		  return new JsonResponse([
	      'status' => 'error',
	      'message' => 'Database error',
	      'details' => $e->getMessage(),
	    ]);
	  }
		$in_card = array();
		$registered = array();
	    foreach ($results as $row) {
		    if ($row->card == "1") {
			    $in_card[] = $row->meal_id;
		    } else {
			    $registered[] = $row->meal_id;
		    }
	    }
		return new JsonResponse([
			'status' => 'success',
			'in_card' => $in_card,
			'registered' => $registered,
			'all' => $results,
			// 'activities' => join(',', array_keys($results)),
		]);
	}

	public function mealCheckout(Request $request) {
		$init = $this->initCiviCRM();
		$obj = json_decode($init->getContent());
		if ($obj->status != 'success') {
	    \Drupal::logger('civi_get_data')->error($e->getMessage());
		  return new JsonResponse([
	      'status' => 'error',
	      'message' => 'mealCheckout getContent error',
	      'details' => '',
	    ]);
			return $init;
		}

	  // Get logged-in contact ID
		$contact_id = \CRM_Core_Session::singleton()->getLoggedInContactID();
 		if (!$contact_id) {
	    \Drupal::logger('civi_get_data')->error($e->getMessage());
		  return new JsonResponse([
	      'status' => 'error',
	      'message' => 'mealCheckout getLoggedInContactID error',
	      'details' => '',
	    ]);
		  return new JsonResponse([
				'status' => 'error',
				'message' => 'User not logged in'
			]);
	  }

		$civiDb = Database::getConnection('default', 'civicrm');

		// get activities:
		$sql = "
		SELECT
			a.activity_date_time AS date_time,
			a.id AS id,
			cf.total_amount_1381 as total_amount,
			a.subject
		FROM civicrm_activity_contact ac
		INNER JOIN civicrm_activity a ON a.id = ac.activity_id
			AND ac.record_type_id = '3'
			AND a.activity_type_id = '178'
			AND a.status_id = '17'
			AND DATE(a.activity_date_time) = CURDATE()
		LEFT JOIN civicrm_value_registration__243 cf ON cf.entity_id = a.id
		WHERE ac.contact_id = :contact_id 
		ORDER BY a.activity_date_time DESC
	";
	  try {
	    $results = $civiDb->query($sql, [
					':contact_id' => $contact_id,
					//':event_id' => $event_id,
				])->fetchAllAssoc('id');
	  }
	  catch (Exception $e) {
	    \Drupal::logger('civi_get_data')->error($e->getMessage());
		  return new JsonResponse([
	      'status' => 'error',
	      'message' => 'Database error',
	      'details' => $e->getMessage(),
	    ]);
	  }
	  $total_sum = 0;
	    foreach ($results as $row) {
		    $total_sum += $row->total_amount;
	    }
		return new JsonResponse([
			'status' => 'success',
			'message' => $results,
			'total_amount' => $total_sum,
			'activities' => join(',', array_keys($results)),
			'base_url' => $request->getSchemeAndHttpHost() . '/en',
		]);
	}
	
	public function mealRegistration(Request $request) {
		$init = $this->initCiviCRM();
		$obj = json_decode($init->getContent());
		if ($obj->status != 'success') {
			return $init;
		}

	  // Get logged-in contact ID
		$contact_id = \CRM_Core_Session::singleton()->getLoggedInContactID();
 		if (!$contact_id) {
		  return new JsonResponse([
				'status' => 'error',
				'message' => 'User not logged in'
			]);
	  }
	  // Read meal_id from a GET param:
	  $meal_id = $request->query->get('meal_id');
	  if (!$meal_id || $meal_id === 'null') {
		  return new JsonResponse([
			  'status' => 'success',
			  'message' => 'Missing meal_id',
		  ]);
	  }
		$civiDb = Database::getConnection('default', 'civicrm');

		// get activity data
		$sql = "
		SELECT
			ca.id AS meal_id
			, ca.subject as subject
			, r.adult_price1_1401 as adult_price
			, r.adult_vegetarian_price1_1400 as adult_vegetarian_price
			, r.without_food_price1_1399 as without_food_price
			, r.child_price1_1398 as child_price
			, r.take_away_price_1431 as take_away_price
			, r.take_away_vegetarian_price_1432 as take_away_vegetarian_price
			, r.take_away_closing_order_1798 as take_away_closing_order
			, r.percent_1395 as percent
			, r.early_registration_closing_date_1393 as early_registration_closing_date
			, r.sitting_with_families_1422 as sitting_with_families
			, e.display_name_event_1466    display_name_he
			, e.display_name_event_en_1581 display_name_en
			, e.display_name_event_ru_1582 display_name_ru
			, e.display_name_event_es_1583 display_name_es
			, e.content_1452 content_he
			, e.content_en_1578 content_en
			, e.content_ru_1579 content_ru
			, e.content_es_1580 content_es
		FROM civicrm_activity ca
		JOIN civicrm_value_registration__243 r ON r.entity_id = :meal_id
		JOIN civicrm_value_registration__248 e ON e.entity_id = :meal_id
		WHERE ca.id = :meal_id 
	";
	  try {
	    $results = $civiDb->query($sql, [
					':meal_id' => $meal_id,
				])->fetchAssoc('id');
	  }
	  catch (Exception $e) {
	    \Drupal::logger('civi_get_data')->error($e->getMessage());
		  return new JsonResponse([
	      'status' => 'error',
	      'message' => 'Database error',
	      'details' => $e->getMessage(),
	    ]);
	  }
		return new JsonResponse([
			'status' => 'success',
			'meal_data' => $results,
		]);
	}

	public function optionsPage(Request $request) {
		$init = $this->initCiviCRM();
		$obj = json_decode($init->getContent());
		if ($obj->status != 'success') {
			return $init;
		}

	  // Get logged-in contact ID
		$contact_id = \CRM_Core_Session::singleton()->getLoggedInContactID();
 		if (!$contact_id) {
		  return new JsonResponse([
				'status' => 'error',
				'message' => 'User not logged in',
				'base_url' => $request->getSchemeAndHttpHost(),
			]);
	  }

	  // Read event_id from a GET param:
	  $event_id = $request->query->get('event_id');
	  if (!$event_id || $event_id === 'null') {
		  return new JsonResponse([
			  'status' => 'success',
				'activities' => [1],
			  'message' => 'Missing event_id',
				'base_url' => $request->getSchemeAndHttpHost(),
		  ]);
	  }

		$civiDb = Database::getConnection('default', 'civicrm');
		
	  $type = $request->query->get('type') ?? 182;
	  $sql = "
	    SELECT
	      a.id,
	      CASE WHEN registration_option1_title_1553 != '' THEN 1 ELSE 0 END AS option1,
	      CASE WHEN registration_option2_title_1554 != '' THEN 1 ELSE 0 END AS option2,
	      CASE WHEN registration_option3_title_1555 != '' THEN 1 ELSE 0 END AS option3,
	      CASE WHEN registration_option4_title_1556 != '' THEN 1 ELSE 0 END AS option4,
	      CASE WHEN registration_option5_title_1800 != '' THEN 1 ELSE 0 END AS option5,
	      CASE WHEN registration_option6_title_1804 != '' THEN 1 ELSE 0 END AS option6
	    FROM civicrm_activity a
	    JOIN civicrm_activity_contact ac ON a.id = ac.activity_id AND ac.record_type_id = 3 AND ac.contact_id = :contact_id
	    JOIN civicrm_value_registration__248 e ON a.id = e.entity_id AND e.event_id_1448 = :event_id
	  ";
	  if ($type == 182) {
		  $sql .= " JOIN civicrm_contribution c ON e.id_for_payment_1460 = c.id AND c.contribution_status_id = 1 ";
	  }
	  $sql .= " WHERE a.activity_type_id = :type ";
	  try {
	    $results = $civiDb->query($sql, [
					':contact_id' => $contact_id,
					':event_id' => $event_id,
					':type' => $type,
				])->fetchAllAssoc('id');

	    $options = [0, 0, 0, 0, 0, 0];
	    foreach ($results as $row) {
				$options[0] = $options[0] === 0 ? (!empty($row->option1) ? 1 : 0) : $options[0];
				$options[1] = $options[1] === 0 ? (!empty($row->option2) ? 1 : 0) : $options[1];
				$options[2] = $options[2] === 0 ? (!empty($row->option3) ? 1 : 0) : $options[2];
				$options[3] = $options[3] === 0 ? (!empty($row->option4) ? 1 : 0) : $options[3];
				$options[4] = $options[4] === 0 ? (!empty($row->option5) ? 1 : 0) : $options[4];
				$options[5] = $options[5] === 0 ? (!empty($row->option6) ? 1 : 0) : $options[5];
	    }
	  }
	  catch (Exception $e) {
	    \Drupal::logger('civi_get_data')->error($e->getMessage());
		  return new JsonResponse([
	      'status' => 'error',
	      'message' => 'Database error',
	      'details' => $e->getMessage(),
				'base_url' => $request->getSchemeAndHttpHost(),
	    ]);
	  }

		$sql = "
	    SELECT e.*
	    FROM civicrm_activity a
	    LEFT JOIN civicrm_value_registration__248 e ON a.id = e.entity_id
   	    WHERE a.id = :event_id
	    AND DATE(STR_TO_DATE(e.event_date_1450, '%Y-%m-%d %H:%i:%s')) > NOW() - INTERVAL 10 DAY
	";

		try {
			$activities = $civiDb->query($sql, [':event_id' => $event_id])->fetchAllAssoc('id');
	  }
	  catch (Exception $e) {
	    \Drupal::logger('civi_get_data')->error($e->getMessage());
		  return new JsonResponse([
	      'status' => 'error',
	      'message' => $e->getMessage(),
				'base_url' => $request->getSchemeAndHttpHost(),
	    ]);
		}

		return new JsonResponse([
	    'status' => 'success',
		  'event_id' => $event_id,
		  'contact_id' => $contact_id,
	    'options' => $options,
	    'activities' => $activities,
			'base_url' => $request->getSchemeAndHttpHost(),
	  ]);
	}
	
	private function initCiviCRMRequire($filename) {
		try {
			// Find CiviCRM installation
			$civicrm_root = DRUPAL_ROOT . '/sites/default/civicrm/';
			
			// Check if the config file exists
			if (file_exists($civicrm_root . $filename)) {
				return $civicrm_root . $filename;
			}
			// Try alternative locations
			$possible_paths = [
				DRUPAL_ROOT . '/web/sites/default/civicrm/',
				DRUPAL_ROOT . '/../vendor/civicrm/civicrm-core/',
				DRUPAL_ROOT . '/libraries/civicrm/',
				'/var/www/civicrm/'  // Common server location
			];
			
			foreach ($possible_paths as $path) {
				if (file_exists($path . $filename)) {
					return $path . $filename;
				}
			}
		} catch (Exception $e) {
			return NULL;
		}
		return NULL;
	}

	private function initCiviCRM() {
		// Check if CiviCRM module exists
		if (!\Drupal::moduleHandler()->moduleExists('civicrm')) {
			return new JsonResponse([
				'status' => 'error',
				'message' => 'CiviCRM module not enabled'
			]);
		}
		$res = $this->initCiviCRMRequire('civicrm.config.php');
		if ($res === null) {
			return new JsonResponse([
				'status' => 'error',
				'message' => 'civicrm.config.php' . ' not found'
			]);
		}
		if (is_string($res)) {
			require_once $res;
		}
  		$res = $this->initCiviCRMRequire('CRM/Core/Config.php');
		if ($res === null) {
			return new JsonResponse([
				'status' => 'error',
				'message' => 'CRM/Core/Config.php' . ' not found'
			]);
		}
		if (is_string($res)) {
			require_once $res;
		}
		\CRM_Core_Config::singleton();

		return new JsonResponse([
			'status' => 'success'
		]);
	}

}

