<!---

  QDCS_finished_config.cfm

  Revision History:

    6/12/2003	rhowe		Added support for Packlet shortages.

--->


<!--- Module for QDCS Virtual Finished Config feedback Screen --->

<CFSETTING ENABLECFOUTPUTONLY=YES>

<!---
  Useful manual SQL statements:

delete from material_change where query_number=218884;
delete from query_history where query_number=218884;
delete from query where query_number=218884;
--->


<CF_check_login>

  <CFQUERY name="item_query" datasource="#datasource#" username="#username#" password="#password#">
    select product_code, release, parent_serial_number, work_order, position 
      from item 
     where serial_number = <CFQUERYPARAM value="#item_sno#">
  </CFQUERY>
  <CFIF #item_query.recordcount# eq 0>
    <CFSET StructDelete(session, "product_code")>
    <CF_display_error_screen message='No data found for serial number "#item_sno#".' abort="yes" show_previous="no">
  </CFIF>
  <CFSET session.product_code = #item_query.product_code#>

  <CFQUERY name="line_item_rev_query" datasource="#datasource#" username="#username#" password="#password#">
      select max(spec_revision) spec_revision from line_item 
       where work_order = <CFQUERYPARAM value="#item_query.work_order#">
  </CFQUERY>
  <CFIF #line_item_rev_query.recordcount# eq 0>
    <CFSET StructDelete(session, "product_code")>
    <CF_display_error_screen message='No SPEC data found for serial number "#item_sno#".' abort="yes" show_previous="no">
  </CFIF>

  <!--- Get info on this frame --->
  <CFQUERY name="line_item_frame_query" datasource="#datasource#" username="#username#" password="#password#">
      select job_number, frame_type, frame_number, spec from line_item 
       where work_order = <CFQUERYPARAM value="#item_query.work_order#"> 
         and equipment_type = 'FR' 
         and spec_revision = <CFQUERYPARAM value="#line_item_rev_query.spec_revision#">
  </CFQUERY>

  <!--- Get config info on what shelfs should eventually be mounted in this frame --->
  <CFQUERY name="line_item_shelfs_query" datasource="#datasource#" username="#username#" password="#password#">
      select shelf, orig_product_code, status, shelf_layer from line_item 
       where work_order = <CFQUERYPARAM value="#item_query.work_order#"> 
         and equipment_type = 'SH' 
         and spec_revision = <CFQUERYPARAM value="#line_item_rev_query.spec_revision#"> 
     order by shelf desc
  </CFQUERY>

  <!--- Get shelfs that are currently mounted in this frame --->
  <CFQUERY name="mounted_shelfs_query" datasource="#datasource#" username="#username#" password="#password#">
    select serial_number, product_code, position, bay, subposition, shelf_layer, release 
      from item where parent_serial_number = <CFQUERYPARAM value="#item_sno#"> 
     order by position desc
  </CFQUERY>





<!--- START - Main functionality --->

<CFOUTPUT>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN" "http://www.w3.org/TR/REC-html40/loose.dtd">
<HTML>
<HEAD>

<STYLE type="text/css">
<!--
  .packBlackFont {
    color: black;
    font-family: "Courier New CE", "MS Mincho", "Times New Roman", Times, serif;
    font-size: 10pt;
  }
  .packBlueFont {
    color: blue;
    font-family: "Courier New CE", "MS Mincho", "Times New Roman", Times, serif;
    font-size: 10pt;
  }
  .badPackLinkFontSize {
    color: white;
    font-family: Arial, geneva, Helvetica, "sans serif";
    font-size: 10pt;
  }
  .blackFontSize4 {
    color: black;
    font-family: "Times New Roman", Times, serif;
    font-weight: bold;
    font-size: 13pt;
  }
  .packletBlackFont {
    color: black;
    font-family: "Courier New CE", "MS Mincho", "Times New Roman", Times, serif;
    font-size: 7pt;
  }
  .packletBlueFont {
    color: blue;
    font-family: "Courier New CE", "MS Mincho", "Times New Roman", Times, serif;
    font-size: 7pt;
  }
  .smallLinkFontSize {
<!---    color: ##48c64f;--->
    color: ##ff6600;
    font-family: "Lucida Sans Unicode", Arial, geneva, Helvetica, "sans serif";
    font-weight: bold;
    font-size: 8pt;
  }
-->
</STYLE>

    <TITLE>QDCS Finished Config</TITLE>
    <META http-equiv="Content-Style-Type" content="text/css">
    <META http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</HEAD>


<BODY #bgcolorTag# #backgroundTag#>

  <H4><center>
  #line_item_frame_query.job_number# &nbsp;&nbsp; #line_item_frame_query.frame_type# &nbsp;-&nbsp; #line_item_frame_query.frame_number#<br>
  Work Order:&nbsp;&nbsp;#item_query.work_order#
  <H3>

  </CFOUTPUT>

  <CFSET frame = '#line_item_frame_query.frame_type#' & ' '>
  <CFIF len(line_item_frame_query.frame_type) LT 3>
    <CFSET frame = frame & ' '>
  </CFIF>
  <CFSET frame = frame & '#line_item_frame_query.frame_number#'>

  <CFQUERY name="get_existing_sq_query" datasource="#squery_datasource#" username="#squery_username#" password="#squery_password#">
    select query_number from query 
     where job_number = <CFQUERYPARAM value="#line_item_frame_query.job_number#"> 
       and frame = <CFQUERYPARAM value="#frame#"> 
       and problem like ('QDCS Config generated shortage query.%')
  </CFQUERY>
  <CFIF get_existing_sq_query.recordcount NEQ 0>
    <CFOUTPUT>
      A shortage Shop Query (#get_existing_sq_query.query_number#) has already been entered for this frame.<br>
      <br>
      You may not "Finish" this frame again.<br>
      Any more Shortages you wish to add,<br>
      must be done manually through the ShopQuery tool.<br>
    </CFOUTPUT>
    <CFEXIT>
  </CFIF>


  <CFSET num_shortages = 0>

  <CFLOOP index="shelf_loop" from="1" to="#mounted_shelfs_query.recordcount#">

    <CFQUERY name="mounted_cps_query" datasource="#datasource#" username="#username#" password="#password#">
      select serial_number, product_code, position, bay, subposition, shelf_layer, release 
        from item where parent_serial_number = <CFQUERYPARAM value="#mounted_shelfs_query.serial_number[shelf_loop]#"> 
         and product_code = 'SHORT' 
       order by shelf_layer, subposition, position
    </CFQUERY>

    <CFLOOP index="pack_loop" from="1" to="#mounted_cps_query.recordcount#">

      <CFSET num_shortages = num_shortages + 1>

      <CFIF num_shortages EQ 1>  <!--- First item, so must create the main ShopQuery --->

        <!--- Get a new Shop Query Number --->
        <CFQUERY name="get_sq_num_query" datasource="#squery_datasource#" username="#squery_username#" password="#squery_password#">
          select nextquery.nextval num from dual
        </CFQUERY>
        <!--- Insert query into QUERY table --->
        <CFQUERY name="insert_sq_query" datasource="#squery_datasource#" username="#squery_username#" password="#squery_password#">
          insert into query (query_number, serial_number, work_order, status, shift, job_number, 
                             cd_date, spec, frame, severity, current_assign_login_id, problem) 
                 values (#get_sq_num_query.num#, '#item_query.work_order#', '#item_sno#', 'O', 1, '#line_item_frame_query.job_number#', 
                         sysdate, '#line_item_frame_query.spec#', '#frame#', 'Minor', 
                         '500008', 'QDCS Config generated shortage query.')
        </CFQUERY>
        <!--- Insert query into QUERY_HISTORY table --->
        <CFQUERY name="insert_sq_history_query" datasource="#squery_datasource#" username="#squery_username#" password="#squery_password#">
          insert into query_history (query_number, login_id, event) 
                 values (#get_sq_num_query.num#, '#session.operator_id#', 'Create')
        </CFQUERY>
        <CFQUERY name="insert_sq_history_query" datasource="#squery_datasource#" username="#squery_username#" password="#squery_password#">
          insert into query_history (query_number, login_id, event) 
                 values (#get_sq_num_query.num#, '500008', 'Assign')
        </CFQUERY>
      </CFIF>

      <CFIF mounted_cps_query.subposition[pack_loop] EQ '1'>
        <CFSET pack_face = 'R'>
      <CFELSE>
        <CFSET pack_face = 'F'>
      </CFIF>
      <CFIF mounted_cps_query.shelf_layer[pack_loop] NEQ ''>
        <CFQUERY name="line_item_position_query" datasource="#datasource#" username="#username#" password="#password#">
          select shelf, slot, lc_pos, orig_product_code, cpc, face, status, shelf_layer, equipment_type 
            from line_item 
           where work_order = <CFQUERYPARAM value="#item_query.work_order#"> 
             and TO_NUMBER(shelf) = <CFQUERYPARAM value="#mounted_shelfs_query.position[shelf_loop]#"> 
             and TO_NUMBER(slot)  = <CFQUERYPARAM value="#mounted_cps_query.position[pack_loop]#"> 
             and equipment_type in ('CP','LD','PH') 
             and face = <CFQUERYPARAM value="#pack_face#"> 
             and shelf_layer = <CFQUERYPARAM value="#mounted_cps_query.shelf_layer[pack_loop]#"> 
             and spec_revision = <CFQUERYPARAM value="#line_item_rev_query.spec_revision#">
        </CFQUERY>
      <CFELSE>
        <CFQUERY name="line_item_position_query" datasource="#datasource#" username="#username#" password="#password#">
          select shelf, slot, lc_pos, orig_product_code, cpc, face, status, shelf_layer, equipment_type 
            from line_item 
           where work_order = <CFQUERYPARAM value="#item_query.work_order#"> 
             and TO_NUMBER(shelf) = <CFQUERYPARAM value="#mounted_shelfs_query.position[shelf_loop]#"> 
             and TO_NUMBER(slot)  = <CFQUERYPARAM value="#mounted_cps_query.position[pack_loop]#"> 
             and equipment_type in ('CP','LD','PH') 
             and face = <CFQUERYPARAM value="#pack_face#"> 
             and spec_revision = <CFQUERYPARAM value="#line_item_rev_query.spec_revision#">
        </CFQUERY>
      </CFIF>

      <!--- Add this material change to the Shop Query --->
      <CFQUERY name="insert_sq_material_query" datasource="#squery_datasource#" username="#squery_username#" password="#squery_password#">
        insert into material_change (query_number, cpc, pec, qty, shelf, slot, add_del, frame, spec, lc_pos, face, shelf_layer) 
               values (#get_sq_num_query.num#, '#line_item_position_query.cpc#', '#line_item_position_query.orig_product_code#', 
                       1, '#mounted_shelfs_query.position[shelf_loop]#', '#line_item_position_query.slot#', 'D', '#frame#', 
                       '#line_item_frame_query.spec#', '#line_item_position_query.lc_pos#', 
                       '#line_item_position_query.face#', '#line_item_position_query.shelf_layer#')
      </CFQUERY>


      <!--- For Packlet-Holders, we must also generate SHORTages for the packlets below it --->
      <CFIF line_item_position_query.equipment_type EQ 'PH'>

        <CFIF mounted_cps_query.shelf_layer[pack_loop] NEQ ''>
          <CFQUERY name="line_item_pl_position_query" datasource="#datasource#" username="#username#" password="#password#">
          select shelf, slot, lc_pos, orig_product_code, cpc, face, status, shelf_layer, equipment_type 
            from line_item 
           where work_order = <CFQUERYPARAM value="#item_query.work_order#"> 
             and TO_NUMBER(shelf) = <CFQUERYPARAM value="#mounted_shelfs_query.position[shelf_loop]#"> 
             and TO_NUMBER(slot)  = <CFQUERYPARAM value="#mounted_cps_query.position[pack_loop]#"> 
             and equipment_type = 'PL' 
             and face = <CFQUERYPARAM value="#pack_face#"> 
             and shelf_layer = <CFQUERYPARAM value="#mounted_cps_query.shelf_layer[pack_loop]#"> 
             and spec_revision = <CFQUERYPARAM value="#line_item_rev_query.spec_revision#">
          </CFQUERY>
        <CFELSE>
          <CFQUERY name="line_item_pl_position_query" datasource="#datasource#" username="#username#" password="#password#">
          select shelf, slot, lc_pos, orig_product_code, cpc, face, status, shelf_layer, equipment_type 
            from line_item 
           where work_order = <CFQUERYPARAM value="#item_query.work_order#"> 
             and TO_NUMBER(shelf) = <CFQUERYPARAM value="#mounted_shelfs_query.position[shelf_loop]#"> 
             and TO_NUMBER(slot)  = <CFQUERYPARAM value="#mounted_cps_query.position[pack_loop]#"> 
             and equipment_type = 'PL' 
             and face = <CFQUERYPARAM value="#pack_face#"> 
             and spec_revision = <CFQUERYPARAM value="#line_item_rev_query.spec_revision#">
          </CFQUERY>
        </CFIF>

        <CFLOOP index="packlet_loop" from="1" to="#line_item_pl_position_query.recordcount#">
          <CFSET num_shortages = num_shortages + 1>
          <!--- Add this material change to the Shop Query --->
          <CFQUERY name="insert_sq_material_query" datasource="#squery_datasource#" username="#squery_username#" password="#squery_password#">
            insert into material_change (query_number, cpc, pec, qty, shelf, slot, add_del, frame, spec, lc_pos, face, shelf_layer) 
                   values (#get_sq_num_query.num#, '#line_item_pl_position_query.cpc[packlet_loop]#', 
                           '#line_item_pl_position_query.orig_product_code[packlet_loop]#', 
                           1, '#mounted_shelfs_query.position[shelf_loop]#', '#line_item_pl_position_query.slot[packlet_loop]#', 'D', '#frame#', 
                           '#line_item_frame_query.spec#', '#line_item_pl_position_query.lc_pos[packlet_loop]#', 
                           '#line_item_pl_position_query.face[packlet_loop]#', '#line_item_pl_position_query.shelf_layer[packlet_loop]#')
          </CFQUERY>
        </CFLOOP>

      </CFIF>  <!--- end of if line_item_position_query.equipment_type EQ 'PH' --->

    </CFLOOP>

  </CFLOOP>

  <CFIF num_shortages EQ 0>
    <CFOUTPUT>
    <br>
    No shortages found on this frame.<br>
    </CFOUTPUT>
  <CFELSE>
    <CFOUTPUT>
    <br>
    Shortage Shop Query number:  #get_sq_num_query.num# has been created.<br>
    </CFOUTPUT>
  </CFIF>
  <CFOUTPUT>
  <br>
  <br>
  <br>
  Finished Configuration.<br>
  <br>
</BODY>
</HTML>

</CFOUTPUT>
