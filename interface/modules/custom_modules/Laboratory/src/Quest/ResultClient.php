<?php
/**
 * @package   	WMT
 * @subpackage	Laboratory
 * @author    	Ron Criswell <ron.criswell@medtechsvcs.com>
 * @copyright 	Copyright (c)2023 Medical Technilogy Services <https://medtechsvcs.com/>
 * @license   	https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace WMT\Laboratory\Quest;

require_once 'ResultServices.php';
require_once 'ParserHL7v23.php';

use function mdts\ToTime;
use function mdts\LogError;
use function mdts\LogException;

use mdts\objects\Laboratory;

/**
 * class ResultClient submits lab order (HL7 messages) to the MedPlus Hub
 * platform.  Encapsulates the sending of an HL7 order to a Quest Lab
 * via the Hub�s SOAP Web service.
 *	
 */
class ResultClient {
	/**
	 * Will pass the username and password to establish a service connection to
	 * the hub. Facilitates packaging the order in a proper HL7 format. Performs
	 * the transmission of the order to the Hub's SOAP Web Service. Provides
	 * method calls to the Results Web Service to retrieve lab results.
	 * 
	 */
	private $STATUS; // D=development/training  V=validation  P=production
	private $ENDPOINT;
	//		https://cert.hub.care360.com/observation/result/service?wsdl
	//		https://hubservices.medplus.com/observation/result/service?wsdl
	private $USERNAME;
	private $PASSWORD;
	private $SENDING_APPLICATION;
	private $SENDING_FACILITY;
	private $RECEIVING_APPLICATION;
	private $RECEIVING_FACILITY;
	private $WSDL;
		
	// data storage   	
	private $service = null;
   	private $request = null;
   	private $response = null;
   	private $messages = array();
   	private $documents = array();
	
   	/**
	 * Constructor for the 'result client' class.
	 */
	public function __construct($lab_id) {
		// retrieve processor data
		$this->lab_id = $lab_id;
		$lab_data = new Laboratory($lab_id);
		
		$this->REPOSITORY = $GLOBALS['oer_config']['documents']['repository'];
			
		$this->STATUS = 'D'; // default training
		if ($lab_data->DorP == 'P') $this->STATUS = 'P'; // production
		$this->SENDING_APPLICATION = $lab_data->send_app_id;
		$this->SENDING_FACILITY = $lab_data->send_fac_id;
		$this->RECEIVING_APPLICATION = $lab_data->recv_app_id;
		$this->RECEIVING_FACILITY = $lab_data->recv_fac_id;
		$this->ENDPOINT = $lab_data->remote_host;
		$this->USERNAME = $lab_data->login;
		$this->PASSWORD = $lab_data->password;
		$this->WSDL = $lab_data->remote_host.$lab_data->results_path;
			
		$category = sqlQuery("SELECT `id` FROM `categories` WHERE `name` LIKE ?", array($lab_data->name));
		$this->DOCUMENT_CATEGORY = $category['id'];

		// sanity check
		if ($lab_data->protocol != 'WS' ||
			$lab_data->type != 'quest' ||
				!$this->DOCUMENT_CATEGORY ||
 				!$this->RECEIVING_APPLICATION ||
				!$this->RECEIVING_FACILITY ||
				!$this->SENDING_APPLICATION ||
				!$this->SENDING_FACILITY ||
				!$this->WSDL ||
				!$this->USERNAME ||
				!$this->PASSWORD ||
				!$this->ENDPOINT ||
				!$this->STATUS ||
				!$this->REPOSITORY )
			die ("Quest Interface Not Properly Configured!!\n\n<pre>".var_dump($this)."</pre>\n\n");

		// web service initialization
		$options = array();
		$options['wsdl_local_copy'] = 'wsdl_quest_results';
		$options['wsdl_path'] = $GLOBALS["OE_SITE_DIR"]."/labs/".$lab_id;
		$options['login'] = $this->USERNAME;
		$options['password'] = $this->PASSWORD;

		$this->service = new ObservationResultService($this->WSDL,$options);
		$this->request = new ObservationResultRequest();
		$this->response = new ObservationResultResponse();	

		return;
	}

	/**
	 *
 	 * Retrieve result 
 	 * This routine dispatches to the correct retrieval routine based on
 	 * the protocol type specified for the current processor (lab).
	 *
	 */
	public function getResults($max_messages = 1, $start_date = false, $end_date = false, $debug = false) {
		$response = null;
		$results = array();
		$response_id = null;
		$more_results = false;
		$this->messages = array();
			
		try {
			$this->request->retrieveFinalsOnly = TRUE;
			$this->request->maxMessages = $max_messages;
			if ($start_date) $this->request->startDate = $start_date;
			if ($end_date) $this->request->endDate = $end_date;
			
			$response = $this->service->getResults($this->request);
			$response_id = $response->requestId;
			$more_results = $response->isMore;
			$results = $response->observationResults;

/* ////////////////// DEBUG /////////////////////
$HL7Message = <<<EOD
MSH|^~\&|LAB|MET||94109021|20220102131409||ORU^R01|80000000002368912676|P|2.3.1
PID|1|1|HL136672P|194942|DEMO^DONNY||20010101|M|||23 OAK CREST CT^^MANVEL^TX^77578||^^^^^832^2076399|||||3853113|639948274
ORC|RE|194942|HL136672P||CM|||||||123456789^LOVE^STRANGE^^^^^^^^^^NPI
OBR|1|194942|HL136672P|14577^BV/VAGINITIS PANEL DNA PROBE^^14577SB=^BV/VAGINITIS PANEL DNA PROBE|||20211230164900|||||||20211231024800||123456789^LOVE^STRANGE^^^^^^^^^^NPI|||||IG^Quest Diagnostics-Dallas Lab^4770 Regent Blvd^Irving^TX^75063-2445^Dr. Robert L Breckenridge|20220102121000|||F
OBX|1|ST|6568-0^T vaginalis rRNA Genital Ql Probe^LN^70000125^TRICHOMONAS:^QDIDAL||NOT DETECTED||NOT DETECTED|N|||F|||20220102121000|IG
OBX|2|ST|6410-5^G vaginalis rRNA Genital Ql Probe^LN^70000130^GARDNERELLA:^QDIDAL||NOT DETECTED||NOT DETECTED|N|||F|||20220102121000|IG
OBX|3|ST|47000-5^Candida rRNA Vag Ql Probe^LN^70000135^CANDIDA:^QDIDAL||NOT DETECTED||NOT DETECTED|N|||F|||20220102121000|IG
EOD;
$HL7Message = <<<EOD
MSH|^~\&|LAB|MET||94109021|20220106021230||ORU^R01|80000000002371825019|P|2.3.1
PID|1|1|HL136672P|194942|DEMO^DONNY||20010101|M|||23 OAK CREST CT^^MANVEL^TX^77578||^^^^^832^2076399|||||3853113|639948274
ORC|RE|195710|HL227889P||CM|||||||1528554102^GIBSON^SHERRELL^^^^^^^^^^NPI
OBR|1|195710|HL227889P|91431^HIV 1/2 ANTIGEN/ANTIBODY,FOURTH GENERATION W/RFL^^91431XRGA=^HIV 1/2 ANTIGEN/ANTIBODY,FOURTH GENERATION W/RFL|||20220104104200|||||||20220105002500||1528554102^GIBSON^SHERRELL^^^^^^^^^^NPI|||||RGA^Quest Diagnostics-Houston Lab^5850 Rogerdale Road^Houston^TX^77072-1602^Robert L Breckenridge|20220106011000|||F
OBX|1|TX|56888-1^HIV 1+2 Ab+HIV1 p24 Ag SerPl Ql IA^LN^86009052^HIV AG/AB, 4TH GEN^QDIDAL||NON-REACTIVE||NON-REACTIVE|N|||F|||20220106011000|RGA
NTE|1||HIV-1 antigen and HIV-1/HIV-2 antibodies were not
NTE|2||detected. There is no laboratory evidence of HIV
NTE|3||infection.
NTE|4|| 
NTE|5||PLEASE NOTE: This information has been disclosed to
NTE|6||you from records whose confidentiality may be
NTE|7||protected by state law.  If your state requires such
NTE|8||protection, then the state law prohibits you from
NTE|9||making any further disclosure of the information
NTE|10||without the specific written consent of the person
NTE|11||to whom it pertains, or as otherwise permitted by law.
NTE|12||A general authorization for the release of medical or
NTE|13||other information is NOT sufficient for this purpose.
NTE|14||  
NTE|15||For additional information please refer to
NTE|16||http://education.questdiagnostics.com/faq/FAQ106
NTE|17||(This link is being provided for informational/
NTE|18||educational purposes only.)
NTE|19|| 
NTE|20|| 
NTE|21||The performance of this assay has not been clinically
NTE|22||validated in patients less than 2 years old.
NTE|23|| 
OBR|2|195710|HL227889P|8477^GLUCOSE, GESTATIONAL SCREEN (50G)-135 CUTOFF^^8477XRGA=^GLUCOSE, GESTATIONAL SCREEN (50G)-135 CUTOFF|||20220104104200|||||||20220105002500||1528554102^GIBSON^SHERRELL^^^^^^^^^^NPI|||||RGA^Quest Diagnostics-Houston Lab^5850 Rogerdale Road^Houston^TX^77072-1602^Robert L Breckenridge|20220106011000|||F
OBX|1|NM|1504-0^Glucose 1h p 50 g Glc PO SerPl-mCnc^LN^25014900^GLUCOSE, GESTATIONAL SCREEN (50G)-135 CUTOFF^QDIDAL||178|mg/dL|<135|H|||F|||20220106011000|RGA
NTE|1|| 
NTE|2||One hour value of > or = 135 mg/dL indicates the 
NTE|3||need for a diagnostic 75 g dose 2-hour or 
NTE|4||100 g dose 3-hour oral glucose tolerance test;
NTE|5||patient fasting is required. 
OBR|3|195710|HL227889P|6399^CBC (INCLUDES DIFF/PLT)^^6399XRGA=^CBC (INCLUDES DIFF/PLT)|||20220104104200|||||||20220105002500||1528554102^GIBSON^SHERRELL^^^^^^^^^^NPI|||||RGA^Quest Diagnostics-Houston Lab^5850 Rogerdale Road^Houston^TX^77072-1602^Robert L Breckenridge|20220106011000|||F
OBX|1|NM|6690-2^WBC # Bld Auto^LN^30000000^WHITE BLOOD CELL COUNT^QDIDAL||6.4|Thousand/uL|3.8-10.8|N|||F|||20220106011000|RGA
OBX|2|NM|789-8^RBC # Bld Auto^LN^30000100^RED BLOOD CELL COUNT^QDIDAL||4.03|Million/uL|3.80-5.10|N|||F|||20220106011000|RGA
OBX|3|NM|718-7^Hgb Bld-mCnc^LN^30000200^HEMOGLOBIN^QDIDAL||11.5|g/dL|11.7-15.5|L|||F|||20220106011000|RGA
OBX|4|NM|4544-3^Hct VFr Bld Auto^LN^30000300^HEMATOCRIT^QDIDAL||34.4|%|35.0-45.0|L|||F|||20220106011000|RGA
OBX|5|NM|787-2^MCV RBC Auto^LN^30000400^MCV^QDIDAL||85.4|fL|80.0-100.0|N|||F|||20220106011000|RGA
OBX|6|NM|785-6^MCH RBC Qn Auto^LN^30000500^MCH^QDIDAL||28.5|pg|27.0-33.0|N|||F|||20220106011000|RGA
OBX|7|NM|786-4^MCHC RBC Auto-mCnc^LN^30000600^MCHC^QDIDAL||33.4|g/dL|32.0-36.0|N|||F|||20220106011000|RGA
OBX|8|NM|788-0^RDW RBC Auto-Rto^LN^30000700^RDW^QDIDAL||13.5|%|11.0-15.0|N|||F|||20220106011000|RGA
OBX|9|NM|777-3^Platelet # Bld Auto^LN^30000800^PLATELET COUNT^QDIDAL||211|Thousand/uL|140-400|N|||F|||20220106011000|RGA
OBX|10|NM|776-5^PMV Bld Rees-Ecker^LN^30004600^MPV^QDIDAL||9.6|fL|7.5-12.5|N|||F|||20220106011000|RGA
OBX|11|NM|751-8^Neutrophils # Bld Auto^LN^30001700^ABSOLUTE NEUTROPHILS^QDIDAL||4083|cells/uL|1500-7800|N|||F|||20220106011000|RGA
OBX|12|ST|26507-4^Neuts Band # Bld^LN^30001110^ABSOLUTE BAND NEUTROPHILS^QDIDAL||DNR|cells/uL|0-750|N|||X|||20220106011000|RGA
OBX|13|ST|30433-7^Metamyelocytes # Bld^LN^30001310^ABSOLUTE METAMYELOCYTES^QDIDAL||DNR|cells/uL|0|N|||X|||20220106011000|RGA
OBX|14|ST|30446-9^Myelocytes # Bld^LN^30001510^ABSOLUTE MYELOCYTES^QDIDAL||DNR|cells/uL|0|N|||X|||20220106011000|RGA
OBX|15|ST|26523-1^Promyelocytes # Bld^LN^30001530^ABSOLUTE PROMYELOCYTES^QDIDAL||DNR|cells/uL|0|N|||X|||20220106011000|RGA
OBX|16|NM|731-0^Lymphocytes # Bld Auto^LN^30002110^ABSOLUTE LYMPHOCYTES^QDIDAL||1926|cells/uL|850-3900|N|||F|||20220106011000|RGA
OBX|17|NM|742-7^Monocytes # Bld Auto^LN^30002400^ABSOLUTE MONOCYTES^QDIDAL||320|cells/uL|200-950|N|||F|||20220106011000|RGA
OBX|18|NM|711-2^Eosinophil # Bld Auto^LN^30002700^ABSOLUTE EOSINOPHILS^QDIDAL||38|cells/uL|15-500|N|||F|||20220106011000|RGA
OBX|19|NM|704-7^Basophils # Bld Auto^LN^30003000^ABSOLUTE BASOPHILS^QDIDAL||32|cells/uL|0-200|N|||F|||20220106011000|RGA
OBX|20|ST|30376-8^Blasts # Bld^LN^30003500^ABSOLUTE BLASTS^QDIDAL||DNR|cells/uL|0|N|||X|||20220106011000|RGA
OBX|21|ST|30392-5^nRBC # Bld^LN^30003610^ABSOLUTE NUCLEATED RBC^QDIDAL||DNR|cells/uL|0|N|||X|||20220106011000|RGA
OBX|22|NM|770-8^Neutrophils/leuk NFr Bld Auto^LN^30000900^NEUTROPHILS^QDIDAL||63.8|%||N|||F|||20220106011000|RGA
OBX|23|ST|764-1^Neuts Band/leuk NFr Bld Manual^LN^30001100^BAND NEUTROPHILS^QDIDAL||DNR|%||N|||X|||20220106011000|RGA
OBX|24|ST|740-1^Metamyelocytes/leuk NFr Bld Manual^LN^30001300^METAMYELOCYTES^QDIDAL||DNR|%||N|||X|||20220106011000|RGA
OBX|25|ST|749-2^Myelocytes/leuk NFr Bld Manual^LN^30001500^MYELOCYTES^QDIDAL||DNR|%||N|||X|||20220106011000|RGA
OBX|26|ST|783-1^Promyelocytes/leuk NFr Bld Manual^LN^30001520^PROMYELOCYTES^QDIDAL||DNR|%||N|||X|||20220106011000|RGA
OBX|27|NM|736-9^Lymphocytes/leuk NFr Bld Auto^LN^30001800^LYMPHOCYTES^QDIDAL||30.1|%||N|||F|||20220106011000|RGA
OBX|28|ST|13046-8^Variant Lymphs/leuk NFr Bld^LN^30002000^REACTIVE LYMPHOCYTES^QDIDAL||DNR|%|0-10|N|||X|||20220106011000|RGA
OBX|29|NM|5905-5^Monocytes/leuk NFr Bld Auto^LN^30002200^MONOCYTES^QDIDAL||5.0|%||N|||F|||20220106011000|RGA
OBX|30|NM|713-8^Eosinophil/leuk NFr Bld Auto^LN^30002500^EOSINOPHILS^QDIDAL||0.6|%||N|||F|||20220106011000|RGA
OBX|31|NM|706-2^Basophils/leuk NFr Bld Auto^LN^30002800^BASOPHILS^QDIDAL||0.5|%||N|||F|||20220106011000|RGA
OBX|32|ST|709-6^Blasts/leuk NFr Bld Manual^LN^30003400^BLASTS^QDIDAL||DNR|%||N|||X|||20220106011000|RGA
OBX|33|ST|19048-8^nRBC/100 WBC Bld-Rto^LN^30003600^NUCLEATED RBC^QDIDAL||DNR|/100 WBC|0|N|||X|||20220106011000|RGA
OBX|34|ST|8251-1^Service Cmnt-Imp^LN^30004200^COMMENT(S)^QDIDAL||DNR|||N|||X|||20220106011000|RGA
OBR|4|195710|HL227889P|799^RPR (MONITOR) W/REFL TITER^^799XRGA=^RPR (MONITOR) W/REFL TITER|||20220104104200|||||||20220105002500||1528554102^GIBSON^SHERRELL^^^^^^^^^^NPI|||||RGA^Quest Diagnostics-Houston Lab^5850 Rogerdale Road^Houston^TX^77072-1602^Robert L Breckenridge|20220106011000|||F
OBX|1|ST|20507-0^RPR Ser Ql^LN^40010100^RPR (MONITOR) W/REFL TITER^QDIDAL||NON-REACTIVE||NON-REACTIVE|N|||F|||20220106011000|RGA
OBR|5|195710|HL227889P|395^CULTURE, URINE, ROUTINE^^395XRGA=^CULTURE, URINE, ROUTINE|||20220104104200|||||||20220105002500||1528554102^GIBSON^SHERRELL^^^^^^^^^^NPI|||||RGA^Quest Diagnostics-Houston Lab^5850 Rogerdale Road^Houston^TX^77072-1602^Robert L Breckenridge|20220106011000||MI|F
OBX|1|TX|630-4^Bacteria Ur Cult^LN^75400002^CULTURE, URINE, ROUTINE^QDIDAL||SEE NOTE||||||F|||20220106011000|RGA
NTE|1
NTE|2||  CULTURE, URINE, ROUTINE 
NTE|3||  
NTE|4||  Micro Number:      12017505
NTE|5||  Test Status:       Final
NTE|6||  Specimen Source:   Urine
NTE|7||  Specimen Quality:  Adequate
NTE|8||  Result:            Growth of mixed flora was isolated, suggesting
NTE|9||                     probable contamination. No further testing will
NTE|10||                     be performed. If clinically indicated, 
NTE|11||                     recollection using a method to minimize
NTE|12||                     contamination, with prompt transfer to Urine
NTE|13||                     Culture Transport Tube, is recommended.
EOD;
$HL7Message = <<<EOD
MSH|^~\&|LAB|MET||6126000|20220106211305||ORU^R01|80000000002372783144|P|2.3.1
PID|1|124830|HL271478P|196070|MARTINEZ^LESLIE||20010426|F|||11434 DAVENWOOD DR^^HOUSTON^TX^77089|||||||4002572
ORC|RE|196070|HL271478P||CM|||||||1588984520^WAY^ANTONIA^^^^^^^^^^NPI
OBR|1|196070|HL271478P|14577^BV/VAGINITIS PANEL DNA PROBE^^14577CLSB=^BV/VAGINITIS PANEL DNA PROBE|||20220105141400||||G|||20220106021900||1588984520^WAY^ANTONIA^^^^^^^^^^NPI|||||IG^Quest Diagnostics-Dallas Lab^4770 Regent Blvd^Irving^TX^75063-2445^Dr. Robert L Breckenridge|20220106201100|||F
OBX|1|ST|6568-0^T vaginalis rRNA Genital Ql Probe^LN^70000125^TRICHOMONAS:^QDIDAL||NOT DETECTED||NOT DETECTED|N|||F|||20220106201100|IG
OBX|2|ST|6410-5^G vaginalis rRNA Genital Ql Probe^LN^70000130^GARDNERELLA:^QDIDAL||NOT DETECTED||NOT DETECTED|N|||F|||20220106201100|IG
OBX|3|ST|47000-5^Candida rRNA Vag Ql Probe^LN^70000135^CANDIDA:^QDIDAL||NOT DETECTED||NOT DETECTED|N|||F|||20220106201100|IG
OBX|4|TX|^^^10001531^COMMENT^QDIDAL||||||||F|||20220106201100|IG
NTE|1||We received an Affirm Transport Device with a
NTE|2||non-specific order. Based upon the specimen
NTE|3||submitted, the BV/Vaginitis DNA Screen was
NTE|4||performed. If this is not what you intended to
NTE|5||order, please contact your local client service
NTE|6||representative immediately so that we can adjust
NTE|7||our billing appropriately. You may also inquire
NTE|8||about alternative or additional testing.
OBR|2|196070|HL271478P|11363^CHLAMYDIA/N. GONORRHOEAE RNA, TMA, UROGENITAL^^11363XRGA=^CHLAMYDIA/N. GONORRHOEAE RNA, TMA, UROGENITAL|||20220105141400|||||||20220106021900||1588984520^WAY^ANTONIA^^^^^^^^^^NPI|||||RGA^Quest Diagnostics-Houston Lab^5850 Rogerdale Road^Houston^TX^77072-1602^Robert L Breckenridge|20220106201100|||F
OBX|1|ST|43304-5^C trach rRNA Spec Ql NAA+probe^LN^70043800^CHLAMYDIA TRACHOMATIS RNA, TMA, UROGENITAL^QDIDAL||NOT DETECTED||NOT DETECTED|N|||F|||20220106201100|RGA
OBX|2|ST|43305-2^N gonorrhoea rRNA Spec Ql NAA+probe^LN^70043900^NEISSERIA GONORRHOEAE RNA, TMA, UROGENITAL^QDIDAL||NOT DETECTED||NOT DETECTED|N|||F|||20220106201100|RGA
OBX|3|TX|^^^10001597^COMMENT^QDIDAL||||||||F|||20220106201100|RGA
NTE|1||The analytical performance characteristics of this
NTE|2||assay, when used to test SurePath(TM) specimens have been
NTE|3||determined by Quest Diagnostics. The modifications have
NTE|4||not been cleared or approved by the FDA. This assay has
NTE|5||been validated pursuant to the CLIA regulations and is
NTE|6||used for clinical purposes.
NTE|7|| 
NTE|8||For additional information, please refer to
NTE|9||https://education.questdiagnostics.com/faq/FAQ154
NTE|10||(This link is being provided for information/
NTE|11||educational purposes only.)
NTE|12|| 
EOD;
$result = new ObservationResult();
$result->documents = null;
$result->HL7Message = str_replace("\n","\r",$HL7Message);
$result->observationResultType = 'LAB';
$result->resultId = 'bc2f47c98fb64f00b5b505ab8fd8bd16';
$results[] = $result;
//////////////////// DEBUG ///////////////////// */

			echo "\n".count($results)." Results Returned";
			if ($more_results) echo " (MORE RESULTS)";
			if ($debug) {
				if (count($results)) echo "\nHL7 Messages:";
			}
				
			if ($results) {
				foreach ($results as $result) {
					if ($debug) {
						echo "\n" . $result->HL7Message;
						$options = array('debug'=>true);
					}

					$parser = new Parser_HL7v23($result->HL7Message,$options);
					$parser->parse();
					$message = $parser->getRequest();
				
					$message->message_id = $result->resultId;
					$message->response_id = $response_id;
					$message->documents = $result->documents;
					$message->hl7message = $result->HL7Message;

					// add the message to the results
					$this->messages[] = $message;
				}
			}
		} catch (Exception $e) {
			die("FATAL ERROR: " . $e->getMessage());
		}
			
		return $this->messages;
	}
		
	/**
	 * buildResultAck() constructs a valid HL7 Order result message string.
 	 *
 	 * @access public
 	 * @param int $max maximum number of result to retrieve
 	 * @param string[] $data array of order data
 	 * @return Order $order
 	 * 
	 */
	public function buildResultAck($result_id, $reject = FALSE) {
		$ack = new AcknowledgedResult();
			
		$ack->resultId = $result_id;
		$ack->ackCode = "CA"; // assume okay
		$ack->rejectionReason = '';
			
		if ($reject) {
			$ack->ackCode = "CR"; // reject
			$ack->rejectionReason = $reject;
		}

		return $ack;
	}
		
	/**
	 *
 	 * The sendResultAck() method will:
 	 *
	 * 1. Create a proxy for making SOAP calls
	 * 2. Create an ACK request object
	 * 3. Submit calling Acknowledgment()
	 *
	 */
	public function sendResultAck($id, $acks, $debug = false) {
		$response = null;
		$this->request = new Acknowledgment();
		$this->request->requestId = $id;
		$this->request->acknowledgedResults = $acks;
		
		try {
			if ($debug) {
				echo "\n".count($acks)." Result Acknowledgments Sent";
			}
			
			$response = $this->service->acknowledgeResults($this->request);
		} 
		catch (Exception $e) {
			echo ($e->getMessage());
		}
			
		return;
	}
		
		
	public function getProviderAccounts() {
		$results = array();
		try {
			$results = $this->service->getProviderAccounts();
			echo "\n".count($results)." Results Returned";
			
			echo "\nProviders:";
			var_dump($results);
		} 
		catch (Exception $e) {
			echo($e->getMessage());
		}
			
		return;
	}
		
}
