#! /usr/bin/perl
use DBI;
#----------------------------------------------------------------------------------
# Program to create a shipment_data_file from the "Shipped" items in the DB.
# This must be run every 24 hours or some items may be skipped or duplicated.
#
# Usage:  ./create_defects_report.pl
#
# Rob Howe  07/12/2002
#----------------------------------------------------------------------------------


# DEBUG levels: 0=no output, 1=general output, 2=detailed output
my $DEBUG = 2;

$inputBasedir = "/usr2/DATAVAULT/data/DEFECTS/in";
$doneBasedir  = "/usr2/DATAVAULT/data/DEFECTS/done";
$AddressInfoFile = $ARGV[0];
if (($AddressInfoFile eq '') || ($AddressInfoFile eq 'clear')) {
  $AddressInfoFile = 'defect_serial_numbers';
}

$FILENAME_DATE = substr(`date +%Y%m%d%H%M%S`,0,-1);

#if ($DEBUG) {
  print "------------------------------------------------------------------\n";
  print " $FILENAME_DATE Executing $0 with input file=$AddressInfoFile.\n";
#}

$DefectsDataFile = '>>/usr2/DATAVAULT/data/DEFECTS/defect_report.' . $FILENAME_DATE;

#----------------------------------------------------------------------------------
# Connect to the database
#----------------------------------------------------------------------------------
my $dbh = DBI->connect('DBI:Oracle:proj1.world', 'u1', 'p1') or die "ERROR:  Cannot connect to the database";
$dbh->{AutoCommit} = 0;


#----------------------------------------------------------------------------------
# Get all the SNo's for items that were shipped in the last 24 hours
#----------------------------------------------------------------------------------
my $check_new_query = $dbh->prepare_cached("select distinct SERIAL_NUMBER, product_code, TO_CHAR(time_stamp,\'YYYYMMDD\') time_stamp from item") or die "Couldn't prepare statement: " . sth->errstr;

#----------------------------------------------------------------------------------
# Get the job_number for the shipped item's SNo given
#----------------------------------------------------------------------------------
my $get_line_item_query = $dbh->prepare_cached('select distinct UPPER(li.job_number) job_number, UPPER(li.dash) dash, UPPER(li.spec) spec from item i, line_item li where i.SERIAL_NUMBER = ? and i.work_order = li.work_order') or die "Couldn't prepare statement: " . sth->errstr;;

#----------------------------------------------------------------------------------
# Get the item info for the SNo given
#----------------------------------------------------------------------------------
my $get_item_query = $dbh->prepare_cached('select parent_serial_number, ship_loose, UPPER(job_id) job_id, UPPER(spec) spec from item where SERIAL_NUMBER = ?') or die "Couldn't prepare statement: " . sth->errstr;

#----------------------------------------------------------------------------------
# Get all the SNo's mounted in the given item serial_number
#----------------------------------------------------------------------------------
my $get_mounted_query = $dbh->prepare_cached('select SERIAL_NUMBER, product_code from item where parent_SERIAL_NUMBER = ?') or die "Couldn't prepare statement: " . sth->errstr;




  my $retval = 0;

  open defects_outfile, $DefectsDataFile;
  $defects_record_count = 0;

  print defects_outfile "Job_Number|Frame_Serial_Number|Inspection_Time|Cell|Stage|Frame_Product_Code|Total_Defects|Defect_Count|Defect_Status|Defect_Name|Bad_Product_Code|Bad_CPC|Supplier|Reporting_Operator|Reporting_Comment\n";

  $check_new_query->execute();
  while (@frame_history_records = $check_new_query->fetchrow_array()) {

    $frame_sno          = $frame_history_records[0];
    $frame_product_code = $frame_history_records[1];
    $frame_time_stamp   = $frame_history_records[2];

    $get_item_query->execute($frame_sno);
    my (@item_record) = $get_item_query->fetchrow_array();
    $parent_frame_sno = $item_record[0];
    $frame_ship_loose = $item_record[1];
    $frame_job_number = $item_record[2];
    $frame_dash       = '';
    $frame_spec       = $item_record[3];

    if ($frame_ship_loose ne 'Y') {
      if ($DEBUG > 1) {
        print " Getting non-shiploose line_item info for $frame_sno.\n";
      }
      $get_line_item_query->execute($frame_sno);
      my (@line_item_record) = $get_line_item_query->fetchrow_array();
      $frame_job_number = $line_item_record[0];
      $frame_dash       = $line_item_record[1];
      $frame_spec       = substr($line_item_record[2],0,4);

#     If this item is a frame, create a CCIF file:
      if ($parent_frame_sno le '11') {
#        $retval = create_ccif_file();
      }
    }

#   Create job_number + dash variable
    $frame_job_number_dash = $frame_job_number;
    if ($frame_spec ne 'MO') {
      if (($frame_dash ne '') && ($frame_dash ne '00')) {
        if (substr($frame_dash,0,1) eq '0') {
          $frame_job_number_dash = $frame_job_number_dash . substr($frame_dash,1);
        } else {
          $frame_job_number_dash = $frame_job_number_dash . $frame_dash;
        }
      }
    }


    if ($DEBUG) {
      print "Frame record:  $frame_sno, $frame_cpc, $frame_time_stamp, job_number=$frame_job_number, dash=$frame_dash, spec=$frame_spec, shiploose=$frame_ship_loose.\n";
    }

    $retval = look_for_defects($frame_sno);

    $retval = recursive_do_children($frame_sno);
  }

  print defects_outfile "TRAILER $defects_record_count\n";
  close defects_outfile;


  disconnect_handles();
  close address_infile;

# Move finished Cust Info file to the "done" directory
  $mv_cmd_arg1 = $inputBasedir . '/' . $AddressInfoFile;
  $mv_cmd_arg2 = $doneBasedir . '/' . $AddressInfoFile . '_' . $FILENAME_DATE;
  @mv_cmd = ('/bin/mv', $mv_cmd_arg1, $mv_cmd_arg2);
  system (@mv_cmd) == 0 or print "ERROR:  Failed command:  @mv_cmd.\n";

  if ($DEBUG) {
    print "File created:  $DefectsDataFile  num_records=$defects_record_count.\n";
  }

#if ($DEBUG) {
  $FILENAME_DATE = substr(`date +%Y%m%d%H%M%S`,0,-1);
  print " $FILENAME_DATE Finished executing $0.\n";
  print "------------------------------------------------------------------\n";
#}


#----------------------------------------------------------------------------------
# Functions:
#----------------------------------------------------------------------------------

sub recursive_do_children {
  local($parent_serial_number) = @_;

  $get_mounted_query->execute($parent_serial_number);
  my $mounted_counter = 0;
  my @mounted_array;
  while (my @mounted_records = $get_mounted_query->fetchrow_array()) {
    $mounted_array[$mounted_counter] = $mounted_records[0];
    if ($DEBUG > 2) {
      print "  DBI record:  mounted_counter=$mounted_counter, $mounted_array[$mounted_counter].\n";
    }
    $mounted_counter += 1;
  }
  $get_mounted_query->finish;

  my $mounted_loop = 0;
  while ($mounted_loop < $mounted_counter) {
    my $mounted_serial_number = $mounted_array[$mounted_loop];

    my $retval = look_for_defects($mounted_serial_number);

    $retval = recursive_do_children($mounted_serial_number);
    $mounted_loop += 1;
  }

  return 1;
}


sub look_for_defects {
  local($sno) = @_;

#----------------------------------------------------------------------------------
# Get the item info details for the SNo given
#----------------------------------------------------------------------------------
  my $get_test_ids_query = $dbh->prepare_cached("SELECT distinct(f.test_id) FROM inspection_point i, cell c, stage s, frame_history f, operator o, facility fc WHERE f.frame_serial_number = ? AND fc.facility_id = f.facility_id AND c.cell_id = f.cell_id AND s.stage_id = f.stage_id AND o.operator_id = f.operator_id AND i.inspection_id = f.inspection_id AND f.event_type_id in (6,7)") or die "Couldn't prepare statement: " . sth->errstr;

  $get_test_ids_query->execute($sno);
  while (@test_id_records = $get_test_ids_query->fetchrow_array()) {

    $test_id = $test_id_records[0];

    if ($DEBUG > 1) {
      print "  Item record:  sno=$sno, test_id=$test_id.\n";
    }

    my $get_defects_query = $dbh->prepare_cached("select defect_number, serial_number, cell_id, week_number, pec from defect_history where test_id = ?") or die "Couldn't prepare statement: " . sth->errstr;

    $get_defects_query->execute($test_id);
    while (@defects_records = $get_defects_query->fetchrow_array()) {

      if ($DEBUG > 1) {
        print "  Found defect!  sno=$sno, test_id=$test_id.\n";
      }
      $defect_number = $defects_records[0];
      $retval = output_defect($test_id, $defect_number);

    }
    $get_defects_query->finish;
  }
  $get_test_ids_query->finish;

  return 1;
}


sub output_defect ($$) {
  local($test_id) = $_[0];
  local($defect_number) = $_[1];

  print "  test_id = $test_id, defect_number = $defect_number.\n";

#----------------------------------------------------------------------------------
# Get the defect info details for the defect_number given
#----------------------------------------------------------------------------------
  my $get_defects_query = $dbh->prepare_cached("select serial_number, cell_id, stage_id, week_number, pec, cpc, supplier_id, defect_count, status, defect_code, comments, time_stamp from defect_history where test_id = ? and defect_number = ?") or die "Couldn't prepare statement: " . sth->errstr;

  $get_defects_query->execute($test_id, $defect_number);
  my (@defect_record)  = $get_defects_query->fetchrow_array();
  my $serial_number    = $defect_record[0];
  my $cell_id          = $defect_record[1];
  my $stage_id         = $defect_record[2];
  my $bad_product_code = $defect_record[3];
  my $bad_cpc          = $defect_record[4];
  my $supplier_id      = $defect_record[5];
  my $defect_count     = $defect_record[6];
  my $status           = $defect_record[7];
  my $defect_code      = $defect_record[8];
  my $comments         = $defect_record[9];
  my $inspection_time  = $defect_record[10];

  if (($product_code eq 'SKIP') || ($product_code eq 'SHORT')) {
    if ($DEBUG > 1) {
      print "  Not outputting item sno=$serial_number, pec=$product_code.\n";
    }
    $get_defects_query->finish;
    return 1;
  }

# COEO is the job_number  (ex: 'H3R714')
  my $x_coeo          = substr($frame_job_number,0,8);

# Output to "Defects" file:
# There are 15 columns
  my $defects_output_line = sprintf "%s|%s|%s|%s|%s|%s|%s|%s|%s|%s|%s|%s|%s|%s|%s\n", $frame_job_number, $frame_sno, $inspection_time, $cell_id, $stage_id, $frame_product_code, $defect_count, $defect_count, $status, $defect_code, $bad_product_code, $bad_cpc, $supplier_id, 'OPERATOR', $comments;
  print defects_outfile $defects_output_line;
  $defects_record_count++;

  return 1;
}


sub strip_spaces {
  local($var) = @_;
  $var =~ s/^\s+//;
  $var =~ s/\s+$//;
  return $var;
}


sub disconnect_handles {
  $check_new_query->finish;
  $get_item_query->finish;
  $get_line_item_query->finish;
  $dbh->disconnect();
}
