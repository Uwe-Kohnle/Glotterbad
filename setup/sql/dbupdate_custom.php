<#1>
<?php
	$ilCtrlStructureReader->getStructure();
?>
<#2>
<?php
if( !$ilDB->tableExists('sahs_exchange_pattern') )
{
	$ilDB->createTable('sahs_exchange_pattern',
		array(
			"obj_id" => array(
				"type" => "integer",
				"length" => 4,
				"notnull" => true
			),
			"sco_id" => array(
				"type" => "integer",
				"length" => 4,
				"notnull" => true
			),
			"writable" => array(
				"type" => "integer",
				"length" => 1,
				"notnull" => true,
				"default" => 1
			),
			"pattern" => array(
				"type" => "text",
				"length" => 4000,
				"notnull" => false
			),
			"fields" => array(
				"type" => "integer",
				"length" => 4,
				"notnull" => true
			),
			"active" => array(
				"type" => "integer",
				"length" => 1,
				"notnull" => true,
				"default" => 1
			)
		)
	);
	$ilDB->addPrimaryKey('sahs_exchange_pattern',array('obj_id', 'sco_id'));
}
?>
<#3>
<?php
if( !$ilDB->tableExists('sahs_exchange_object') )
{
	$ilDB->createTable('sahs_exchange_object',
		array(
			"target_obj_id" => array(
				"type" => "integer",
				"length" => 4,
				"notnull" => true
			),
			"target_sco_id" => array(
				"type" => "integer",
				"length" => 4,
				"notnull" => true
			),
			"target_field_id" => array(
				"type" => "integer",
				"length" => 4,
				"notnull" => true
			),
			"source_obj_id" => array(
				"type" => "integer",
				"length" => 4,
				"notnull" => true
			),
			"source_sco_id" => array(
				"type" => "integer",
				"length" => 4,
				"notnull" => true
			),
			"source_field_id" => array(
				"type" => "integer",
				"length" => 4,
				"notnull" => true
			),
			"source_order" => array(
				"type" => "integer",
				"length" => 2,
				"notnull" => false
			),
			"pattern_id" => array(
				"type" => "integer",
				"length" => 2,
				"notnull" => false
			)
		)
	);
	$ilDB->addPrimaryKey('sahs_exchange_object',array('target_obj_id', 'target_sco_id', 'target_field_id'));
}	
?>