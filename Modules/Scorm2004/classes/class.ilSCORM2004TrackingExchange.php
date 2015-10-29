<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once './Modules/Scorm2004/classes/class.ilObjSCORM2004LearningModule.php';
/**
* Class ilSCORM2004TrackingExchange
*
* @author Uwe Kohnle <kohnle@internetlehrer-gmbh.de>
*
* @ingroup ModulesScorm2004
*/
class ilSCORM2004TrackingExchange
{

	function checkIfPatternExists() {
		global $ilDB;
	 	$res = $ilDB->queryF(
			'SELECT obj_id FROM sahs_exchange_pattern WHERE obj_id = %s',
		 	array('integer'),
		 	array($this->object->getId())
		);
		if ($ilDB->numRows($res)) return true;
		return false;
	}
	
	function checkIfWritable() {
		global $ilDB;
	 	$res = $ilDB->queryF(
			'SELECT writable FROM sahs_exchange_pattern WHERE obj_id = %s',
		 	array('integer'),
		 	array($this->object->getId())
		);
		$val_rec = $ilDB->fetchAssoc($res);
		if ($val_rec["writable"] == 1) return true;
		return false;
	}
	
	function getSuspend_data($a_obj_id,$a_user_id) {
		global $ilDB,$ilUser;
	 	$res = $ilDB->queryF(
			'SELECT suspend_data FROM cmi_node, cp_node WHERE slm_id= %s AND cmi_node.cp_node_id = cp_node.cp_node_id and user_id= %s',
		 	array('integer','integer'),
		 	array($a_obj_id,$a_user_id)
		);
		if (!$ilDB->numRows($res)) return "";
		$val_rec = $ilDB->fetchAssoc($res);//nur 1 SCO pro LM
		return $val_rec["suspend_data"];
		// if($auto_last_visited) $status['last_visited'] = $val_rec["last_visited"];
		// if ($ilDB->numRows($res)) return true;
		// return false;
	}
	
	function deletePatternAndObjectsData() {
		global $ilDB;
	 	$res = $ilDB->queryF(
			'DELETE FROM sahs_exchange_pattern WHERE obj_id = %s',
		 	array('integer'),
		 	array($this->object->getId())
		);
	 	$res = $ilDB->queryF(
			'DELETE FROM sahs_exchange_object WHERE target_obj_id = %s',
		 	array('integer'),
		 	array($this->object->getId())
		);
	}
	
	function getLearningModulesWithPattern() {
		global $ilDB;
		$lmTitles = array();
	 	$res = $ilDB->queryF(
			'SELECT sahs_exchange_pattern.obj_id, title FROM sahs_exchange_pattern, il_meta_general WHERE sahs_exchange_pattern.obj_id <> %s AND sahs_exchange_pattern.obj_id=il_meta_general.obj_id',
		 	array('integer'),
		 	array($this->object->getId())
		);
		while($row = $ilDB->fetchAssoc($res))
		{
			$lmTitles[$row['obj_id']] = $row['title'];
		}
		return $lmTitles;
	}
	
	function getSelectedExchangeObjects($a_obj_id) {
		global $ilDB;
		$lmEx = array();
	 	$res = $ilDB->queryF(
			'SELECT * FROM sahs_exchange_object WHERE target_obj_id = %s',
		 	array('integer'),
		 	array($a_obj_id)
		);
		while($row = $ilDB->fetchAssoc($res))
		{
			// $lmEx[$row['target_obj_id'].'.'.$row['target_sco_id'].'.'.$row['target_field_id']] = $row['source_obj_id'].'.'.$row['source_sco_id'].'.'.$row['source_field_id'];
			$lmEx[$row['target_obj_id'].'.'.$row['target_field_id']] = $row['source_obj_id'].'.'.$row['source_field_id'];
		}
		return $lmEx;
	}
	
	function getPossibleExchangeObjects($a_obj_id_ar) {
		global $ilDB;
		$lmTitles = array();
		$lmPattern = array();
		$return_ar=array();
		$s_obj_id=implode(',',$a_obj_id_ar);
		if ($s_obj_id!="") {
			$res = $ilDB->queryF(
				'SELECT sahs_exchange_pattern.obj_id, title, sahs_exchange_pattern.pattern FROM sahs_exchange_pattern, il_meta_general '
				.'WHERE sahs_exchange_pattern.obj_id <> %s AND sahs_exchange_pattern.obj_id in ('.$s_obj_id.') AND sahs_exchange_pattern.obj_id=il_meta_general.obj_id',
				array('integer'),
				array($this->object->getId())
			);
			while($row = $ilDB->fetchAssoc($res))
			{
				$lmTitles[$row['obj_id']] = $row['title'];
				$lmPattern[$row['obj_id']] = $row['pattern'];
			}
			foreach($lmPattern as $lm => $pattern) {
				$tmp=self::getPatternEssentials($pattern);
				for ($i=0; $i<$tmp["p2_i_counter_fields"];$i++) {
					$pos=$tmp["p2_pos"][$i];
					$return_ar[$lm.'.'.$pos]=$lmTitles[$lm].': '.$tmp["p2_c".$pos];
				}
			}
		}
		return $return_ar;
	}
	
	function exchangeTrackingSelectSave($ar_save) {
		global $ilDB;
	 	$res = $ilDB->queryF(
			'DELETE FROM sahs_exchange_object WHERE target_obj_id = %s',
		 	array('integer'),
		 	array($this->object->getId())
		);
		foreach ($ar_save as $key => $value) {
			$source = explode('.',$value);
			$source_obj_id = $source[0];
			$source_field_id = $source[1];
			$ilDB->insert('sahs_exchange_object', 
				array(
					'target_obj_id'		=> array('integer', $this->object->getId()),
					'target_sco_id'		=> array('integer', 0),
					'target_field_id'	=> array('integer', $key),
					'source_obj_id'		=> array('integer', $source_obj_id),
					'source_sco_id'		=> array('integer', 0),
					'source_field_id'	=> array('integer', $source_field_id)
					// 'c_timestamp'	=> array('timestamp', ilUtil::now())
				)
			);
		}
	}
	
	function getPatternAr($a_obj_id) {
		global $ilDB;
		$a_pattern = array();
		$a_pattern["obj_id"] = 0;
		$a_pattern["writable"] = 0;
		$a_pattern["pattern"] = "";
		$a_pattern["fields"] = 0;
		$a_pattern["active"] = 0;
		$a_pattern["c_timestamp"] = "";
		
	 	$res = $ilDB->queryF(
			'SELECT * FROM sahs_exchange_pattern WHERE obj_id = %s',
		 	array('integer'),
		 	array($a_obj_id)
		);
		if (!$ilDB->numRows($res)) return $a_pattern;
		$val_rec = $ilDB->fetchAssoc($res);//nur 1 SCO pro LM
		return $val_rec;
	}

	function patternCreate($b_useSuspend, $i_scoId, $s_pattern) {
		global $ilDB,$ilUser;
		if ($b_useSuspend == true) $s_pattern=self::getSuspend_data($this->object->getId(),$ilUser->getId());
		$pattern_ar=self::getPatternEssentials($s_pattern);
		$i_fields=$pattern_ar["p2_i_counter_fields"];
		$i_writable = 0;
		// if ($b_writable == true) $i_writable = 1;
		
	 	$res = $ilDB->queryF(
			'SELECT sco_id FROM sahs_exchange_pattern WHERE obj_id= %s AND sco_id = %s',
		 	array('integer','integer'),
		 	array($this->object->getId(),$i_scoId)
		);
		if (!$ilDB->numRows($res)) {
			$ilDB->insert('sahs_exchange_pattern', 
				array(
					'obj_id'		=> array('integer', $this->object->getId()),
					'sco_id'		=> array('integer', $i_scoId),
					'pattern'		=> array('text', $s_pattern),
					'fields'		=> array('integer', $i_fields),
					'writable'		=> array('integer', $i_writable),
					'c_timestamp'	=> array('timestamp', ilUtil::now())
				)
			);
		} else {
			$ilDB->update('sahs_exchange_pattern',
				array(
					'pattern'		=> array('text', $s_pattern),
					'fields'		=> array('integer', $i_fields),
					'c_timestamp'	=> array('timestamp', ilUtil::now())
				),
				array(
					'obj_id'		=> array('integer', $this->object->getId()),
					'sco_id'		=> array('integer', $i_scoId)
				)
			);
		}
		return true;
	}

	function patternPropertiesUpdate($i_scoId, $b_writable) {
		global $ilDB,$ilUser;
		$i_writable = 0;
		if ($b_writable == true) $i_writable = 1;
		
		$ilDB->update('sahs_exchange_pattern',
			array(
				'writable'		=> array('integer', $i_writable)				),
			array(
				'obj_id'		=> array('integer', $this->object->getId()),
				'sco_id'		=> array('integer', $i_scoId)
			)
		);
		if ($i_writable == 0) {
			$res = $ilDB->queryF(
				'DELETE FROM sahs_exchange_object WHERE target_obj_id = %s',
				array('integer'),
				array($this->object->getId())
			);
		}
		return true;
	}

	
	function n64() {
		$n64="0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_$";
		return $n64;
	}
	function translateToArticulate3($i2a) {
		if ($i2a < 64) return self::n64()[$i2a];
		else {
			return "~2" . (self::n64()[$i2a%64]) . (self::n64()[$i2a/64]);
		}
	}
	function translateFromArticulate3($sa) {
		if ($sa[0]=="~") {
			return (strpos(self::n64(), substr($sa,3,1)) * 64) + strpos(self::n64(), substr($sa,2,1));
		} else {
			return strpos(self::n64(), substr($sa,0,1));
		}
	}
	function translateFromArticulateWithoutTilde($sa) {
		if (strlen($sa) == 3) $sa = substr($sa,1);
		if (strlen($sa) == 2) return (strpos(self::n64(), substr($sa,1,1)) * 64) + strpos(self::n64(), substr($sa,0,1));
		return strpos(self::n64(), substr($sa,0,1));
	}
	function translateToArticulateWithoutTilde($i2a) {
		if ($i2a < 64) return self::n64()[$i2a];//substr(n64(), $i2a, 1);
		else {
			return "" . (self::n64()[$i2a%64]) . (self::n64()[$i2a/64]);
		}
	}

	function explodeSuspendToArray($s_suspend) {
		//need to get 3 parts
		$pattern_ar = array();
		$cut10=strpos($s_suspend,'~');
		$tmp = strpos($s_suspend,'n6XTG');
		$cut20 = strpos($s_suspend,'~',$tmp);
		// $cut30 = strlen($s_suspend)-4;
		$pattern_ar[0] = substr($s_suspend, 0, $cut10);
		$pattern_ar[1] = substr($s_suspend, $cut10, $cut20-$cut10);
		$pattern_ar[2] = substr($s_suspend, $cut20);
		
		// echo '<br>Gesamtlaenge = '.strlen($s_suspend).', d.h. -3 (Anfang) = '.(strlen($s_suspend)-3).'. Dies entspricht der Pruefsumme '.self::translateFromArticulateWithoutTilde( substr($s_suspend,1,2));
		// echo '<br>Laenge 1. Teil ohne Pruefsumme = '.strlen($pattern_ar[0]);
		// $pr1 = self::translateFromArticulateWithoutTilde( substr($s_suspend,$cut10+2,1));
		// echo '<br>Laenge 2. Teil = '.strlen($pattern_ar[1]). ', d.h. -5 (Anfang) = '.(strlen($pattern_ar[1])-5).'. Dies Entspricht der Pruefsumme (plus Multiplikation von 64: 0,1,2,...)';
		// for ($i=0;$i<4;$i++) {echo ' '.($pr1+64*$i);}
		// echo '<br>Laenge 3. Teil = '.strlen($pattern_ar[2]). ', d.h. -4 (Anfang) und -4 (Ende) = '.(strlen($pattern_ar[2])-8).'. Dies Entspricht der Pruefsumme '.self::translateFromArticulateWithoutTilde( substr($s_suspend,$cut20+2,2));
		return $pattern_ar;
	}

	function getPatternEssentials($s_pattern) {
		$pattern_ar = self::explodeSuspendToArray($s_pattern);
		// p0 no more needed
		$p1 = $pattern_ar[0];
		$p2 = $pattern_ar[1];
		$suspend["p1_init"] = "2";
		$suspend["p1_s_counter_org"] = substr($p1,1,2);
		$suspend["p1_i_counter_org"] = self::translateFromArticulateWithoutTilde( $suspend["p1_s_counter_org"] );
		$suspend["p1_content"] = substr($p1,3);
		$suspend["p2_init"] = "~2";
		$suspend["p2_s_counter_org"] = substr($p2,2,1);
		$suspend["p2_i_counter_org"] = self::translateFromArticulateWithoutTilde( $suspend["p2_s_counter_org"] );
		$suspend["p2_after_init"] = "22";
		$suspend["p2_content_org"] = substr($p2,5);
		$suspend["p3"] = $pattern_ar[2];
		$suspend["p2_content_empty"] = preg_replace('/1\^3#\d\d/','1^',$suspend["p2_content_org"]);
		$suspend["p2_i_counter_empty"] = strlen($suspend["p2_content_empty"]);
		$suspend["p2_s_counter_empty"] = self::translateToArticulateWithoutTilde( $suspend["p2_i_counter_empty"] % 64);
		$suspend["p1_i_counter_empty"] = $suspend["p1_i_counter_org"] - strlen($suspend["p2_content_org"]) + strlen($suspend["p2_content_empty"]);
		$suspend["p1_s_counter_empty"] = self::translateToArticulateWithoutTilde( $suspend["p1_i_counter_empty"] );
		$suspend["p2_pos"] = array();
		$c_ar = preg_split('/1\^/',$suspend["p2_content_org"]);
		for ($i=1; $i<count($c_ar); $i++) {
			if (substr($c_ar[$i],0,2) == "3#") {
				$suspend["p2_pos"][] = $i;
				$suspend["p2_c".$i] = substr($c_ar[$i],1,3);
			}
		}
		$suspend["p2_i_counter_fields"] = count($suspend["p2_pos"]);
		return $suspend;
	}
	
	function createNewSuspend($suspend,$p2_content_new_ar) {
		
		$p2_content_new = implode('1^',$p2_content_new_ar);
		$p2_i_counter_new = strlen($p2_content_new) % 64;
		// $p2_i_counter_new = fmod(strlen($p2_content_new), 64);
		$p2_s_counter_new = self::translateToArticulateWithoutTilde( $p2_i_counter_new);

		$s_return = "";
		$s_return .= $suspend["p1_content"];
		$s_return .= $suspend["p2_init"];
		$s_return .= $p2_s_counter_new;
		$s_return .= "".$suspend["p2_after_init"];
		$s_return .= "".$p2_content_new;
		$s_return .= "".$suspend["p3"];

		$p1_s_counter_new = self::translateToArticulateWithoutTilde( strlen($s_return) );
		
		$s_return = $suspend["p1_init"] . $p1_s_counter_new . $s_return;
		
		// var_dump($p2_content_new);

		return $s_return; 
	}

	function insertTrackingData4Exchange($objId, $userId, $suspend_data) {
		global $ilDB,$ilUser;
		$res = $ilDB->queryF(
			'SELECT cp_node_id FROM cp_node WHERE slm_id = %s and nodename = %s',
			array('integer','text'),
			array($objId,'item')
		);
		$rowtmp=$ilDB->fetchAssoc($res);
		$cp_node_id=$rowtmp['cp_node_id'];
		$cmi_node_id = $ilDB->nextId('cmi_node');
		$a_data=array(
			'accesscount'			=> array('integer', 1),
			'accessduration'		=> array('text', "0"),
			'accessed'				=> array('text', "0"),
			'activityabsduration'	=> array('text', "0"),
			'activityattemptcount'	=> array('integer', 0),
			'activityexpduration'	=> array('text', "0"),
			'activityprogstatus'	=> array('integer', 0),
			'attemptabsduration'	=> array('text', "0"),
			'attemptcomplamount'	=> array('float', 0),
			'attemptcomplstatus'	=> array('integer', 0),
			'attemptexpduration'	=> array('text', "0"),
			'attemptprogstatus'		=> array('integer', 0),
			'audio_captioning'		=> array('integer', 0),
			'audio_level'			=> array('float', 1),
			'availablechildren'		=> array('text', null),
			'cmi_node_id'			=> array('integer', $cmi_node_id),
			'completion'			=> array('float', 0),
			'completion_status'		=> array('text', "incomplete"),
			'completion_threshold'	=> array('text', null),
			'cp_node_id'			=> array('integer', $cp_node_id),
			'created'				=> array('text', "0"),
			'credit'				=> array('text', "credit"),
			'delivery_speed'		=> array('float', 1),
			'c_entry'				=> array('text', "resume"),
			'c_exit'				=> array('text', "suspend"),
			'c_language'			=> array('text', null),
			'launch_data'			=> array('clob', null),
			'learner_name'			=> array('text', $ilUser->getFirstname()." ".$ilUser->getLastname()),
			'location'				=> array('text', null),
			'c_max'					=> array('float', 100),
			'c_min'					=> array('float', 0),
			'c_mode'				=> array('text', null),
			'modified'				=> array('text', "0"),
			'progress_measure'		=> array('float', null),
			'c_raw'					=> array('float', 0),
			'scaled'				=> array('float', 0),
			'scaled_passing_score'	=> array('float', null),
			'session_time'			=> array('text', "PT0S"),
			'success_status'		=> array('text', "unknown"),
			'suspend_data'			=> array('clob', $suspend_data),
			'total_time'			=> array('text', "PT0S"),
			'user_id'				=> array('integer', $userId),
			'c_timestamp'			=> array('timestamp', date('Y-m-d H:i:s')),
			'additional_tables'		=> array('integer', 0)
		);
		$ilDB->insert('cmi_node', $a_data);

	}

	public function initTrackingData($userId, $packageId) {
		global $ilDB,$ilUser,$ilLog;
		//check if soemething to do
		$a_t_pattern=self::getPatternAr($packageId);
		if ($a_t_pattern["writable"] == 0) return;
		// $ilLog->write("DEBUG SQL".$row);

		//check fields
		$a_ex=self::getSelectedExchangeObjects($packageId);
		if(count($a_ex)==0) return;
		
		//check if data available
		$t_suspend = self::getSuspend_data($packageId,$userId);
		if ($t_suspend != "") return;
		
		//get suspend_data of sources
		$a_s_lm=array();
		foreach ($a_ex as $key=>$value) {
			$tmp=explode('.',$value);
			if(!in_array($tmp[0],$a_s_lm)) $a_s_lm[]=$tmp[0];
		}
		$a_s_suspend = array();
		for ($i=0; $i<count($a_s_lm); $i++) {
			$tmp = self::getSuspend_data($a_s_lm[$i],$userId);
			if($tmp != "") $a_s_suspend[$a_s_lm[$i]] = $tmp;
		}
		if (count($a_s_suspend) == 0) return;
		
		//get essentials of target
		$a_t_suspend = self::getPatternEssentials($a_t_pattern["pattern"]);
		$t_suspend_new_ar = preg_split('/1\^/',$a_t_suspend["p2_content_empty"]);
		//get data of sources
		$a_s_suspend_f=array();
		$a_s_suspend_p=array();
		foreach($a_s_suspend as $key => $value) {
			$a_s_suspend_p[$key] = self::getPatternEssentials($value);
			$tmp=$a_s_suspend_p[$key]["p2_content_org"];
			$c_ar = preg_split('/1\^/',$tmp);
			// for($i=0;$i<count($a_ex);$)
			foreach($a_ex as $target=>$source){
				$tmp_s=explode('.',$source);
				if ($tmp_s[0]==$key) {
					$tmp_t=explode('.',$target);
					$t_suspend_new_ar[$tmp_t[1]]=$c_ar[$tmp_s[1]];
				}
			}
		}
		// return;
		//save
		$suspend2insert=self::createNewSuspend(self::getPatternEssentials($a_t_pattern["pattern"]),$t_suspend_new_ar);
		self::insertTrackingData4Exchange($packageId, $userId, $suspend2insert);
	}

}