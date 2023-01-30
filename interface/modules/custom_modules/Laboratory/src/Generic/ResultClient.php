<?php
/**
 * @package   	WMT
 * @subpackage	Laboratory
 * @author    	Ron Criswell <ron.criswell@medtechsvcs.com>
 * @copyright 	Copyright (c)2023 Medical Technilogy Services <https://medtechsvcs.com/>
 * @license   	https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace WMT\Laboratory\Generic;

use Document;
use phpseclib\Net\SFTP;

use OpenEMR\Common\Logging\SystemLogger;

use WMT\Objects\Patient;
use WMT\Objects\Insurance;
use WMT\Classes\Options;
use WMT\Laboratory\Common\Processor;
use WMT\Laboratory\Generic\Parser_HL7v23;
use WMT\Laboratory\Generic\Parser_HL7v251;

/**
 * class ResultClient submits lab order (HL7 messages) in an HL7 order
 */
class ResultClient {
	private $STATUS; // D=development/training, V=validation, P=production
	private $ENDPOINT;
	private $USERNAME;
	private $PASSWORD;
	private $PROTOCOL;
	private $SENDING_APPLICATION;
	private $SENDING_FACILITY;
	private $RECEIVING_APPLICATION;
	private $RECEIVING_FACILITY;
	
	// data storage   	
   	private $request = null;
   	private $response = null;
   	private $messages = array();
   	private $documents = array();
   	
	private $DEBUG = false;
	
   	/**
	 * Constructor for the 'result client' class.
	 */
	public function __construct($lab_id) {
		$this->lab_id = $lab_id;
		$this->logger = new SystemLogger();
		$this->REPOSITORY = $GLOBALS['oer_config']['documents']['repository'];
		
		// retrieve processor data
		$processor = new Processor($lab_id);
		
		// for now !!
		if ($processor->protocol == 'FS2') $processor->protocol = 'FSS';
		if ($processor->protocol == 'FC2') $processor->protocol = 'FSC';
		
		// validate labs repository 
		if (!file_exists($GLOBALS["OE_SITE_DIR"]."/labs")) {
			mkdir($GLOBALS["OE_SITE_DIR"]."/labs");
		}
			
		$this->WORK_DIRECTORY = $GLOBALS["OE_SITE_DIR"]."/labs/".$lab_id."/";
		// validate work directory
		if (!file_exists($this->WORK_DIRECTORY)) {
			mkdir($this->WORK_DIRECTORY);
		}
			
		// validate backup directory
		if (!file_exists($this->WORK_DIRECTORY."backups/")) {
			mkdir($this->WORK_DIRECTORY."backups/");
		}
		
		$this->STATUS = 'D'; // default training
		if ($processor->DorP == 'P') $this->STATUS = 'P'; // production
		$this->SENDING_APPLICATION = $processor->send_app_id;
		$this->SENDING_FACILITY = $processor->send_fac_id;
		$this->RECEIVING_APPLICATION = $processor->recv_app_id;
		$this->RECEIVING_FACILITY = $processor->recv_fac_id;
		$this->RESULTS_PATH = $processor->results_path;
		$this->ENDPOINT = $processor->remote_host;
		$this->PROTOCOL = $processor->protocol;
		$this->PORT = $processor->remote_port;
		$this->USERNAME = $processor->login;
		$this->PASSWORD = $processor->password;
			
		$category = sqlQuery("SELECT `id` FROM `categories` WHERE `name` LIKE ?",array($processor->name));
		if (!$category['id']) {
			$category = sqlQuery("SELECT `id` FROM `categories` WHERE `name` LIKE ?",array('Lab Report'));
		}
		$this->DOCUMENT_CATEGORY = $category['id'];
		
		// sanity check
		if ($processor->protocol == 'DL' || $processor->protocol == 'FSC' || $processor->protocol == 'FC2' || $processor->protocol == 'WS') {
			if ( !$this->DOCUMENT_CATEGORY ||
					!$this->RECEIVING_APPLICATION ||
					!$this->RECEIVING_FACILITY ||
					!$this->SENDING_APPLICATION ||
					!$this->SENDING_FACILITY ||
					!$this->USERNAME ||
					!$this->PASSWORD ||
					!$this->ENDPOINT ||
					!$this->STATUS ||
					!$this->REPOSITORY )
				die ("Result Interface Not Properly Configured [".$processor->protocol."]!!\n\n<pre>".var_dump($this)."</pre>\n\n");
		}
		elseif ($processor->protocol != 'INT') {
			if ( !$this->DOCUMENT_CATEGORY ||
					!$this->RECEIVING_APPLICATION ||
					!$this->RECEIVING_FACILITY ||
					!$this->SENDING_APPLICATION ||
					!$this->SENDING_FACILITY ||
					!$this->RESULTS_PATH ||
					!$this->STATUS ||
					!$this->REPOSITORY )
				die ("Result Interface Not Properly Configured [".$processor->protocol."]!!\n\n<pre>".var_dump($this)."</pre>\n\n");
		}
		else { // internal only
			if ( !$this->DOCUMENT_CATEGORY ||
					!$this->STATUS ||
					!$this->REPOSITORY )
				die ("Order Interface Not Properly Configured [".$processor->protocol."]!!\n\n<pre>".var_dump($this)."</pre>\n\n");
		}
		
		return;
	}
	/**
 	 * Retrieve result 
 	 * This routine dispatches to the correct retrieval routine based on
 	 * the protocol type specified for the current processor (lab).
	 */
	public function getResults($max = 1, $DEBUG = false) {
		$response = null;
		$results = array();
		$this->messages = array();
		switch ($this->PROTOCOL) {
			case 'FSS': // file server
			case 'FS2': // file server
				$this->getFSSResults($max, $DEBUG);
				break;
			case 'FSC': // sFTP 2.3 client
			case 'FC2': // sFTP 2.5.1 client
				$this->getFSCResults($max, $DEBUG);
				break;
			default:
				throw new \Exception("Lab Protocol Not Implemented");
		}
		
		return $this->messages;
	}
	
	/**
	 *
 	 * The ackResult() method mark the result file processed by
 	 * calling the 
 	 *
	 */
	public function ackResult($message, $DEBUG = false) {
		try {
			switch ($message->file_type) {
				case 'FSS':
				case 'FS2':
					$this->ackFSSResult($message, $DEBUG);
					break;
				case 'FSC':
				case 'FC2':
					$this->ackFSCResult($message, $DEBUG);
					break;
				default:
					// processing from backup, do nothing
					break;
			}
		}
		catch (\Exception $e) {
			echo ("\n\nFATAL ERROR: " . $e->getMessage());
		}
				
		return;
	}
			
	/**
	 * Not used with generic interfaces
	 */
	public function sendResultAck($x=false,$y=false,$z=false) {
		return;
	}
	
	/**
	 * File Server (Drop Box) Interface
	 * The client machine provides an sFTP server which allows
	 * the lab to pick up and drop off order/result files.
	 */
	private function getFSSResults($max, $DEBUG) {
		// result directory
		$rdir = $this->RESULTS_PATH;
		$results = array();
		try {
			$new = 0;
			// anything waiting?
			$files = scandir($rdir); // return all contents
			if ($files) {
				foreach ($files AS $fname) {
					// allow either .hl7 or .txt as extensions
					if ( (strpos(strtoupper($fname),'.HL7') === false)
							&& (strpos(strtoupper($fname),'.TXT') === false) 
							&& (strpos(strtoupper($fname),'.GL7') === false) 
							&& (strpos(strtoupper($fname),'.DAT') === false)
					) continue;
					if ($new < $max) {
						// store the contents of the result file
						$new++;
						$results[] = $fname;
					}
					else { // stop fetching and just count records
						$more_results = true;
						break;
					}
				}
			}
			
			echo "\n".$new." Records Available\n";
			if ($more_results) echo " (MORE RESULTS)";
			
			if ($DEBUG) {
				if (count($results)) echo "\nHL7 Messages:";
			}
			
			if (count($results) > 0) {
				foreach ($results as $fname) {
					// check for empty files
					$size = filesize($rdir.$fname);
					if ($size == 0) continue; // skip empty files
					
					// retrieve the result file from the server
					$result = file_get_contents($rdir.$fname);
					if ($result === false) {
						throw new \Exception("Failed to read '$fname' from results directory!!");
					}
				
					$options = '';
					if ($DEBUG) {
						echo "\n" . $result;
						$options = array('debug'=>true);
					}
				
					if ($this->PROTOCOL == 'FSS') {
						$parser = new ParserHL7v23($result,$options);
					} else {
						$parser = new ParserHL7v251($result,$options);
					}
					
					$parser->parse();
					$message = $parser->getMessage();
				
					$message->message_id = $result->resultId;
					$message->response_id = $response_id;
					$message->file_path = $rdir;
					$message->file_name = $fname;
					$message->file_type = $this->PROTOCOL;
					$message->hl7data = $result->HL7Message;
					
					// add the message to the results
					$this->messages[] = $message;
				}
			}
		} 
		catch (\Exception $e) {
			die("\n\nFATAL ERROR: " . $e->getMessage());
		}
		
		return;
	}
	
	/**
	 * Private file server ack processing moves result file from 
	 * sFTP server space to private backup location.
	 * 
	 * @param string $path
	 * @param string $file
	 * @param boolean $DEBUG
	 * @throws \Exception
	 */
	private function ackFSSResult($message, $DEBUG) {
		$rdir = $message->file_path;
		$ldir = $this->WORK_DIRECTORY;
		$bdir = $ldir."backups/";
		$fname = $message->file_name;
		
		if ($message->file_type != 'BACKUP') { 
			if (file_exists($bdir.$fname)) unlink($bdir.$fname); // make sure no old version
			$status = copy ($rdir.$fname, $bdir.$fname);
			if ($status) $status = unlink ($rdir.$fname);
			if ($status === false)
				throw new \Exception("Acknowledging and archiving ('$rdir.$fname')");
		}
		return;
	}
	
	
	/**
	 * File Client (pull) Interface
	 * The lab machine provides an sFTP server which allows
	 * the client to pick up and drop off order/result files.
	 */
	private function getFSCResults($max, $DEBUG) {
		$response = null;
		$results = array();
		$more_results = false;
		$this->messages = array();
		
		// result directorieskk
		$rdir = $this->RESULTS_PATH;
		$ldir = $this->WORK_DIRECTORY;
		$bdir = $ldir."backups/";
			
		try {
			$new = 0;
			$old = 0;
			
			// validate directory
			if (!file_exists($ldir) || !file_exists($bdir)) {
				throw new \Exception("Missing working lab results directory!!");
			}
					
			// anything waiting?
			$files = scandir($ldir); // requeue old records
			if ($files) {
				foreach ($files AS $fname) {
					// allow either .hl7 or .txt as extensions
					if ( (strpos(strtoupper($fname),'.HL7') === false)
							&& (strpos(strtoupper($fname),'.TXT') === false) 
							&& (strpos(strtoupper($fname),'.GL7') === false) 
							&& (strpos(strtoupper($fname),'.DAT') === false)
					) continue;
					
					$results[] = $fname;
					$old++;
				}
			}
			
			echo "\n".$old." Existing Records";
			
			// scanity check before doing anything
			if ( isset($this->USERNAME) && isset($this->PASSWORD) && isset($this->ENDPOINT)) {
				$sftp = new Net_SFTP($this->ENDPOINT,$this->PORT);
				if (!$sftp->login($this->USERNAME, $this->PASSWORD)) {
					throw new \Exception("sFTP session did not initialize!!");
				}
		
				// get result content list
				$sftp->chdir($rdir);
				$newdir = $sftp->pwd();
				$rlist = $sftp->rawlist();
				
				// get results
				if (count($rlist) > 0) {
					foreach ($rlist AS $fname => $fattr) {
						// allow either .hl7 or .txt as extensions
						if ( (strpos(strtoupper($fname),'.HL7') === false)
								&& (strpos(strtoupper($fname),'.TXT') === false) 
								&& (strpos(strtoupper($fname),'.GL7') === false) 
								&& (strpos(strtoupper($fname),'.DAT') === false)
						) continue;
						
						if ($new < $max) {
							// store the contents of the result file
							$new++;
							$results[] = $fname;
							if ($sftp->get($fname,$ldir.$fname) === false) {
								throw new \Exception("Encountered while retrieving '$fname' from server!!");
							}
							// have local copy so delete remote original
							$sftp->delete($fname);
						}
						else { // stop fetching and just count records
							$more_results = true;
						}
					}
				}
			}
			
			echo "\n".$new." Results Returned";
			if ($more_results) echo " (MORE RESULTS)";
			if ($DEBUG) {
				if (count($results) > 0) echo "\nHL7 Messages:";
			}
			// loop through each result record
			if (count($results) > 0) {
				foreach ($results as $fname) {
					// check for empty files
					$size = filesize($ldir.$fname);
					if ($size == 0) continue; // skip empty files
					
					$result = file_get_contents($ldir.$fname);
					if ($result === false) {
						throw new \Exception("Failed to read '$fname' from results directory!!");
					}
				
					$options = '';
					if ($DEBUG) {
						echo "\n" . $result;
						$options = array('debug'=>true);
					}
				
					if ($this->PROTOCOL == 'FSC') $parser = new Parser_HL7v23($result,$options);
					else $parser = new Parser_HL7v251($result,$options);
					
					$parser->parse();
					$message = $parser->getMessage();
				
					$message->message_id = $result->resultId;
					$message->response_id = $response_id;
					$message->file_path = $ldir;
					$message->file_name = $fname;
					$message->file_type = $this->PROTOCOL;
					$message->hl7data = $result->HL7Message;
					
					// add the message to the results
					$this->messages[] = $message;
				}
			}
		} 
		catch (\Exception $e) {
			die("\n\nFATAL ERROR: " . $e->getMessage());
		}
		
		return;
	}
	
	/**
	 * Private file client ack processing moves result file from 
	 * sFTP server space to private backup location.
	 * 
	 * @param string $path
	 * @param string $file
	 * @param boolean $DEBUG
	 * @throws \Exception
	 */
	private function ackFSCResult($message, $DEBUG) {
		$ldir = $message->file_path;
		$fname = $message->file_name;
		
		if ($message->file_type != 'BACKUP') { 
			if (file_exists($ldir.'backups/'.$fname)) unlink($ldir.'backups/'.$fname); // make sure no old version
			$status = copy ($ldir.$fname, $ldir.'/backups/'.$fname);
			if ($status) $status = unlink ($ldir.$fname);
			if ($status === false)
				throw new \Exception("Acknowledging and archiving ('$ldir.$fname')");
		}
		return;
	}
	
	
	/**
	 *
 	 * Repeat processing of result 
	 *
	 */
	public function repeatResults($max = 1, $from = FALSE, $thru = FALSE, $DEBUG = FALSE) {
		$response = null;
		$results = array();
		$more_results = false;
		$this->messages = array();
		// local backup result directory
		$bdir = $this->WORK_DIRECTORY."/backups/";
			
		try {
			// scanity check before doing anything
			if (!file_exists($bdir)) {
				throw new \Exception("Missing backup lab results directory!!");
			}
					
			// get result content list
			$rlist = scandir($bdir); // get dir content list
				
			// get results
			$count = 0;
			if (count($rlist) > 0) {
				foreach ($rlist AS $fname) {
					// allow either .hl7 or .txt as extensions
					if ( (strpos(strtoupper($fname),'.HL7') === false) && (strpos(strtoupper($fname),'.TXT') === false) ) continue;
					$fdate = filemtime($bdir.$fname);
					$last = date('Y-m-d',$fdate);
					if ($last < $from || $last > $thru) continue; // not in selected range
					
					// store the contents of the result file
					$results[$count++] = $fname;
				}
			}
			
			echo "\n".count($results)." Results Qualified";
			if ($more_results) echo " (MORE RESULTS)";
			if ($DEBUG) {
				if (count($results)) echo "\n\nHL7 Messages:";
			}
			
			foreach ($results as $fname) {
				$result = file_get_contents($bdir.$fname);
				if ($result === false) {
					throw new \Exception("Failed to read '$fname' from backup lab directory!!");
				}
				
				if ($DEBUG) echo "\n" . $result;
				
				if ($this->PROTOCOL == 'FS2' || $this->PROTOCOL == 'FC2') 
					$parser = new Parser_HL7v251($result);
				else 
					$parser = new Parser_HL7v23($result);
					
				$parser->parse();
				$message = $parser->getMessage();
				
				$message->message_id = $result->resultId;
				$message->response_id = $response_id;
				$message->file_path = $bdir;
				$message->file_name = $fname;
				$message->file_type = 'BACKUP';
				$message->hl7data = $result;
					
				// add the message to the results
				$this->messages[] = $message;
			}
		} 
		catch (\Exception $e) {
			die("\n\nFATAL ERROR: " . $e->getMessage());
		}
		
		return $this->messages;
	}
	
	public function getProviderAccounts() {
		$results = array();
		try {
			$results = $this->service->getProviderAccounts();
			echo "\n".count($results)." Results Returned";
			
			echo "\nProviders:";
			var_dump($results);
		} 
		catch (\Exception $e) {
			echo "\n\n";
			echo($e->getMessage());
		}
			
		return;
	}
}
