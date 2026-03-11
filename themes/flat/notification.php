
<?php

/*=======================================================\
|                                                        |
|                                                        |
|   Tác giả: Anh Phương                                  |
|   Website:                                             |
|                                                        |
|                                                        |
\=======================================================*/ 

echo '<link href="'.$path_to_root.'/themes/flat/libraries/bootstrap-iso.css" rel="stylesheet" type="text/css">';

class show_notification {

    function get_receipt() {
        global $path_to_root ;
        $today = date2sql(Today());

        $sql = "SELECT COUNT(debtor.debtor_no) AS Icount FROM ".TB_PREF."debtor_trans as trans, ".TB_PREF."debtors_master as debtor, ".TB_PREF."cust_branch as branch WHERE debtor.debtor_no = trans.debtor_no AND trans.branch_code = branch.branch_code AND trans.type = ".ST_SALESINVOICE." AND (trans.ov_amount + trans.ov_gst + trans.ov_freight + trans.ov_freight_tax + trans.ov_discount - trans.alloc) > ".FLOAT_COMP_DELTA." AND trans.due_date<'".$today."' ORDER BY due_date DESC ";

        $result = db_query($sql, "could not get sales type");
        $row = db_fetch_row($result);
        
        echo "<div class='dropdown notification_dropdown' id='receivable'>";
//        echo "<img src='$path_to_root/themes/svgico/get-money.svg' class='notification_img' data-toggle='dropdown' >";
        echo "<i class='material-icons notification_img' data-toggle='dropdown'>file_download</i>";
        echo "<span class='label label-success' data-toggle='dropdown'>".$row[0]."</span>";

        $sql = "SELECT  trans.due_date, debtor.debtor_no, debtor.name,(trans.ov_amount + trans.ov_gst + trans.ov_freight + trans.ov_freight_tax + trans.ov_discount) AS total FROM ".TB_PREF."debtor_trans as trans, ".TB_PREF."debtors_master as debtor,".TB_PREF."cust_branch as branch WHERE debtor.debtor_no = trans.debtor_no AND trans.branch_code = branch.branch_code AND trans.type = ".ST_SALESINVOICE." AND (trans.ov_amount + trans.ov_gst + trans.ov_freight + trans.ov_freight_tax + trans.ov_discount - trans.alloc) > ".FLOAT_COMP_DELTA." AND trans.due_date<'".$today."' ORDER BY due_date DESC LIMIT 10";
        $result = db_query($sql);
        echo "<ul class='dropdown-menu dropdown-menu-right'>";
            echo "<li class='dropdown-header'>"._('Overdue Sales Invoices')."</li>";
                    echo "<li>";
                        while ($myrow = db_fetch($result)) {
                            echo "<a href='$path_to_root/sales/customer_payments.php?customer_id=".$myrow['debtor_no']."' class='dropdown_content'><span class='pull-left'>". $myrow['name']."</span><span class='pull-right'>". price_format($myrow['total'])."</span></a>";
                        }
                    echo "</li>";
            // echo "<li><a href='#' data-toggle='modal' data-target='#myModal'>View all</a></li>";
        echo "</ul>";
        echo "</div>";
    }

    function get_reorder() {
        global $path_to_root;
        include_once($path_to_root . "/includes/session.inc");
        include_once($path_to_root . "/includes/date_functions.inc");
        include($path_to_root . "/includes/ui.inc");

        $today = date2sql(Today());
        $sql2 = "select COUNT(".TB_PREF."stock_moves.stock_id) AS Rcount FROM (".TB_PREF."stock_master, ".TB_PREF."stock_category,".TB_PREF."loc_stock)LEFT JOIN ".TB_PREF."stock_moves ON (".TB_PREF."stock_master.stock_id=".TB_PREF."stock_moves.stock_id) WHERE ".TB_PREF."stock_master.category_id=".TB_PREF."stock_category.category_id AND ".TB_PREF."stock_master.stock_id=".TB_PREF."loc_stock.stock_id AND (".TB_PREF."stock_master.mb_flag='B' OR ".TB_PREF."stock_master.mb_flag='M') AND ".TB_PREF."loc_stock.reorder_level!=0 GROUP BY ".TB_PREF."stock_master.category_id, ".TB_PREF."stock_category.description,".TB_PREF."stock_master.stock_id,".TB_PREF."stock_master.description";
        $result2 = db_query($sql2, "could not get reorder");
        $row = db_fetch_row($result2);
        if($row[0]=='') $row[0] = 0;
        else $row[0] = $row[0];

        function get_trans() {
            $sql = "select ".TB_PREF."stock_master.stock_id, ".TB_PREF."stock_master.description, SUM(IF(".TB_PREF."stock_moves.stock_id IS NULL,0,".TB_PREF."stock_moves.qty)) AS QtyOnHand ,".TB_PREF."loc_stock.reorder_level FROM (".TB_PREF."stock_master, ".TB_PREF."stock_category,".TB_PREF."loc_stock) LEFT JOIN ".TB_PREF."stock_moves ON (".TB_PREF."stock_master.stock_id=".TB_PREF."stock_moves.stock_id) WHERE ".TB_PREF."stock_master.category_id=".TB_PREF."stock_category.category_id AND ".TB_PREF."stock_master.stock_id=".TB_PREF."loc_stock.stock_id AND (".TB_PREF."stock_master.mb_flag='B' OR ".TB_PREF."stock_master.mb_flag='M') AND ".TB_PREF."loc_stock.reorder_level!=0 GROUP BY ".TB_PREF."stock_master.category_id, ".TB_PREF."stock_category.description, ".TB_PREF."stock_master.stock_id, ".TB_PREF."stock_master.description ORDER BY QtyOnHand DESC LIMIT 10";
            return db_query($sql,"No transactions were returned");
        }

        global $path_to_root ;
        echo "<div class='dropdown notification_dropdown' id='re_order'>";

//        echo "<img src='$path_to_root/themes/svgico/online-store.svg' class='notification_img' data-toggle='dropdown'>";
        echo "<i class='material-icons notification_img' data-toggle='dropdown'>shopping_cart</i>";
        echo "<span class='label label-danger' data-toggle='dropdown'>".$row[0]."</span>";

        echo "<ul class='dropdown-menu dropdown-menu-right'>";
            echo "<li class='dropdown-header'>"._('Items below reorder point')."</li>";
            echo "<li>";
                $res = get_trans();
                while ($trans=db_fetch($res)) {
                    if($trans['reorder_level']>$trans['QtyOnHand']) {
                        echo "<a href='$path_to_root/inventory/inquiry/stock_status.php?stock_id=".$trans["stock_id"]."' class='dropdown_content'><span class='pull-left'>". $trans["description"] ."</span><span class='pull-right'>". price_format($trans['QtyOnHand']) ."</span></a>";
                    }
                }
            echo "</li>";
            // echo "<li class='footer'><a href='#' data-toggle='modal' data-target='#myReOModal'>View all</a></li>";
        echo "</ul>";

        echo "</div>";
    }

    function get_payment() {
        
        global $path_to_root ;
        $today = date2sql(Today());
        $sql1 = "SELECT COUNT(s.supplier_id) AS Bcount FROM ".TB_PREF."supp_trans as trans, ".TB_PREF."suppliers as s WHERE s.supplier_id = trans.supplier_id AND trans.type = ".ST_SUPPINVOICE." AND due_date>=".$today." AND (ABS(trans.ov_amount + trans.ov_gst + trans.ov_discount) - trans.alloc) > ".FLOAT_COMP_DELTA." "; 
        $sql1 .= " AND DATEDIFF('$today', trans.due_date) > 0  "; 
        $result1 = db_query($sql1); 
        $row = db_fetch_row($result1);

        $next = date("Y/m/d", mktime(0, 0, 0, date("m")+1 , date("d"),date("Y")));
        $month=date2sql($next);
        $sql1 = "SELECT   trans.tran_date, trans.due_date,s.supp_name,s.supplier_id,(trans.ov_amount + trans.ov_gst + trans.ov_discount) AS total FROM ".TB_PREF."supp_trans as trans, ".TB_PREF."suppliers as s WHERE s.supplier_id = trans.supplier_id AND trans.type = ".ST_SUPPINVOICE." AND due_date>=".$today." AND (ABS(trans.ov_amount + trans.ov_gst + trans.ov_discount) - trans.alloc) > ".FLOAT_COMP_DELTA."";
        $sql1 .= " AND DATEDIFF('$today', trans.due_date) > 0 ORDER BY total DESC LIMIT 10";
        $result1 = db_query($sql1);

        
        echo "<div class='dropdown notification_dropdown' id='payable'>";

//        echo "<img src='$path_to_root/themes/svgico/donate.svg' class='notification_img' data-toggle='dropdown'>";
        echo "<i class='material-icons notification_img' data-toggle='dropdown'>file_upload</i>";
        echo "<span class='label label-warning' data-toggle='dropdown'>".$row[0]."</span>";

        echo "<ul class='dropdown-menu dropdown-menu-right'>";
            echo "<li class='dropdown-header'>"._('Payables within the next 30 days')."</li>";
            echo "<li>";
                while ($myrow1 = db_fetch($result1)) {
                    echo "<a href = '$path_to_root/purchasing/supplier_payment.php?supplier_id=".$myrow1['supplier_id']."' class='dropdown_content'><span class = 'pull-left'>".$myrow1['supp_name']. "</span><span class='pull-right'>".price_format($myrow1['total'])."</span></a>";
                }
            echo "</li>";
            // echo "<li class='footer'><a href='#' data-toggle='modal' data-target='#myPayModel'>View all</a></li>";
        echo "</ul>";

        echo "</div>";
    }
}