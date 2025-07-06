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

	public function mealCheckout(Request $request) {
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
			'base_url' => $request->getSchemeAndHttpHost(),
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
	      CASE WHEN registration_option4_title_1556 != '' THEN 1 ELSE 0 END AS option4
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

			$options = [0, 0, 0, 0];
	    foreach ($results as $row) {
				$options[0] = $options[0] === 0 ? (!empty($row->option1) ? 1 : 0) : $options[0];
				$options[1] = $options[1] === 0 ? (!empty($row->option2) ? 1 : 0) : $options[1];
				$options[2] = $options[2] === 0 ? (!empty($row->option3) ? 1 : 0) : $options[2];
				$options[3] = $options[3] === 0 ? (!empty($row->option4) ? 1 : 0) : $options[3];
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
			AND a.status_id = '2'
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
	
	private function initCiviCRM() {
		// Check if CiviCRM module exists
		if (!\Drupal::moduleHandler()->moduleExists('civicrm')) {
			return new JsonResponse([
				'status' => 'error',
				'message' => 'CiviCRM module not enabled'
			]);
		}
  
		// Try to bootstrap CiviCRM manually
		try {
			// Find CiviCRM installation
			$civicrm_root = DRUPAL_ROOT . '/sites/default/civicrm';
			
			// Check if the config file exists
			if (!file_exists($civicrm_root . '/civicrm.config.php')) {
				// Try alternative locations
				$possible_paths = [
					DRUPAL_ROOT . '/../vendor/civicrm/civicrm-core',
					DRUPAL_ROOT . '/libraries/civicrm',
					'/var/www/civicrm'  // Common server location
				];
				
				$civicrm_root = null;
				foreach ($possible_paths as $path) {
					if (file_exists($path . '/civicrm.config.php')) {
						$civicrm_root = $path;
						break;
					}
				}
				
				if (!$civicrm_root) {
					return new JsonResponse([
						'status' => 'error',
						'message' => 'CiviCRM configuration file not found'
					]);
				}
			}
			
			// Bootstrap CiviCRM
			require_once $civicrm_root . '/civicrm.config.php';
			require_once $civicrm_root . '/CRM/Core/Config.php';
			\CRM_Core_Config::singleton();
			
		} catch (Exception $e) {
			return new JsonResponse([
				'status' => 'error',
				'message' => 'Failed to initialize CiviCRM: ' . $e->getMessage()
			]);
		}

		return new JsonResponse([
			'status' => 'success'
		]);
	}

}

