<?php
/**
 * Common class used to access list options.
 *
 * @package   WMT\Common
 * @author    Ron Criswell <ron@medtechsvcs.com>
 * @copyright Copyright (c)2023 Medical Technilogy Services <https://medtechsvcs.com/>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

/**
 * All new classes are defined in the WMT namespace
 */
namespace WMT\Classes;

/** 
 * Provides general utility functions related to the list_option table and its contents.
 *
 * @package mdts
 * @subpackage Options
 */
class Reports {
	/** 
	 * Class variables
	 */	
	public $id;  // list identifier
	public $list; // content of list by key
	
	public static function do_status($status, $priority, $always=false) {
		$content = "";
		if ($status || $priority) {
			$content .= "<tr><td colspan='4'>\n";
			$content .= "<table class='wmtStatus' style='margin-bottom:10px'><tr>";
			$content .= "<td class='wmtLabel' style='width:50px;min-width:50px'>Status:</td>";
			$content .= "<td class='wmtOutput'>" . ListLook($status, 'Form_Status') . "</td>";
			$content .= "<td class='wmtLabel' style='width:50px;min-width:50px'>Priority:</td>";
			$content .= "<td class='wmtOutput'>" . ListLook($priority, 'Form_Priority') . "</td>\n";
			$content .= "</tr></table></td></tr>\n";
		}
		return $content;
	}

	public static function do_block($data) {
		$content = "";
		if ($data)
			$content = "<tr><td class='wmtOutput' colspan='4'>".$data."</td></tr>\n";
		return $content;
	}

	public static function do_question($question,$data='') {
		$content = "";
		if ($question)
			$content = "<tr><td class='wmtOutput' style='font-size:14px' colspan='3'>".$question."</td><td class='wmtLabel' style='text-align:left;padding-left:20px;width:20%;vertical-align:top;font-size:14px;'>".$data."</td></tr>\n";
		return $content;
	}

	public static function do_text($data, $title='', $always=false) {
		$content = "";
		if ($data || $always) {
			if ($title) {
				$content .= "<tr><td class='wmtLabel'>".str_replace(':','',$title).": </td><td class='wmtOutput' colspan='3' style='white-space:pre-wrap'>".$data."</td></tr>\n";
			}
			else {
				$content .= "<tr><td class='wmtLabel'>&nbsp;</td><td class='wmtOutput' colspan='3' style='white-space:pre-wrap'>".$data."</td></tr>\n";
			}
		}
		return $content;
	}

	public static function do_line($data, $title='', $always=false) {
		$content = "";
		if ($data || $always) {
			if ($title) $title .= ": ";
			$content .= "<td class='text-nowrap font-weight-bold pr-3' style='vertical-align:top'>".$title."</td><td class='text-nowrap' colspan='3'>".$data."</td>\n";
		}
		if ($content) $content = "<tr>".$content."</tr>";
		return $content;
	}

	public static function do_columns($data1, $title1='', $data2=false, $title2='', $always=false) {
		$content = "";
		if ($data1 || $data2 || $always) {
			if ($title1) $title1 .= ": ";
			$content .= "<td class='text-nowrap font-weight-bold pr-3'>".$title1."</td><td class='text-nowrap'>".$data1."</td>\n";
		}
		if ($data2 || $always) {
			if ($title2) $title2 .= ": ";
			$content .= "<td class='text-nowrap font-weight-bold pr-3'>".$title2."</td><td class='text-nowrap'>".$data2."</td>\n";
		}
		if ($content) $content = "<tr>".$content."</tr>";
		return $content;
	}

	public static function do_matrix($matrix=false) {
		$content = "";
		$count = 0;
		if (is_array($matrix)) {
			foreach ($matrix AS $data) {
				if ($data['title']) {
					$title = ($data['title'])? $data['title'].": " : "";
					$content .= "<td class='wmtLabel' ";
					$content .= ($count > 0)? "style='min-width:0;padding-left:20px'" : "style='min-width:0px'";		
					$content .= ">".$title."</td><td class='wmtOutput' style='white-space:nowrap'>".$data['content']."</td>\n";
				}
				$count++;
			}
		}
		if ($content) $content = "<tr><td colspan='4'><table><tr>".$content."</tr></table></td></tr>";
		return $content;
	}
	
	public static function do_blank() {
		$content .= "<tr><td class='wmtLabel' colspan='4' style='height:10px'></td></tr>\n";
		return $content;
	}

	public static function do_break() {
		$content .= "<tr><td colspan='4' style='height:15px'><hr style='border-color:#eee'/></td></tr>\n";
		return $content;
	}

	public static function do_section($data, $title='', $class='') {
		$content = "";
		if ($data) {
			$content = "<fieldset class='border p-2 bg-white'>\n";
			if ($title) {
				$content .= "<legend class='w-auto'>";
				$content .= $title;
				$content .= "</legend>";
			}
			$content .= "<table class='$class'>\n";
			$content .= $data;
			$content .= "</table>\n";
			$content .= "</fieldset>\n";
			
			print $content;
		}
		return;
	}
}
?>
