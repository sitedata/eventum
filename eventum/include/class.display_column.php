<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | Eventum - Issue Tracking System                                      |
// +----------------------------------------------------------------------+
// | Copyright (c) 2003, 2004 MySQL AB                                    |
// |                                                                      |
// | This program is free software; you can redistribute it and/or modify |
// | it under the terms of the GNU General Public License as published by |
// | the Free Software Foundation; either version 2 of the License, or    |
// | (at your option) any later version.                                  |
// |                                                                      |
// | This program is distributed in the hope that it will be useful,      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the        |
// | GNU General Public License for more details.                         |
// |                                                                      |
// | You should have received a copy of the GNU General Public License    |
// | along with this program; if not, write to:                           |
// |                                                                      |
// | Free Software Foundation, Inc.                                       |
// | 59 Temple Place - Suite 330                                          |
// | Boston, MA 02111-1307, USA.                                          |
// +----------------------------------------------------------------------+
// | Authors: Bryan Alsdorf <bryan@mysql.com>                             |
// +----------------------------------------------------------------------+
//
//


/**
 * Class to handle determining which columns should be displayed and in what order
 * on a page (i.e. Issue Listing page).
 * 
 * @author Bryan Alsdorf <bryan@mysql.com>
 */
class Display_Column
{
    /**
     * Returns the columns that should be displayed for the specified page.
     * This method will remove columns that should not be displayed, due to
     * lack of customer integration or insufficient role.
     * 
     * @access  public
     * @param   integer $prj_id The ID of the project.
     * @param   string $page The page to return columns for.
     * @return  array An array of columns that should be displayed.
     */
    function getColumnsToDisplay($prj_id, $page)
    {
        static $returns;
        
        if (empty($returns[$page])) {
            $current_role = Auth::getCurrentRole();
            $data = Display_Column::getSelectedColumns($prj_id, $page);
            $has_customer_integration = Customer::hasCustomerIntegration($prj_id);
            $only_with_customers = array('iss_customer_id');
            
            // remove groups if there are no groups in the system.
            if (count(Group::getAssocList($prj_id)) < 1) {
                unset($data['iss_grp_id']);
            }
            // remove category column if there are no categories in the system
            if (count(Category::getAssocList($prj_id)) < 1) {
                unset($data['prc_title']);
            }
            // remove custom fields column if there are no custom fields
            if (count(Custom_Field::getFieldsToBeListed($prj_id)) < 1) {
                unset($data['custom_fields']);
            }
            // remove customer field if user has a role of customer
            if (Auth::getCurrentRole() == User::getRoleID("Customer")) {
                unset($data['iss_customer_id']);
            }
            
            foreach ($data as $field => $info) {
                // remove fields based on role
                if ($info['min_role'] > $current_role) {
                    unset($data[$field]);
                    continue;
                }
                
                // remove fields based on customer integration
                if (!$has_customer_integration && (in_array($field, $only_with_customers))) {
                    unset($data[$field]);
                    continue;
                }
                // get title
                $data[$field] = Display_Column::getColumnInfo($page, $field);
            }
            $returns[$page] = $data;
        }
        return $returns[$page];
    }
    
    
    /**
     * Returns the columns that have been selected to be displayed on the specified page. This list
     * contains all selected columns, even if they won't actually be displayed.
     * 
     * @access  public
     * @param   integer $prj_id The ID of the project.
     * @param   string $page The page to return columns for.
     * @return  array An array of columns that should be displayed.
     */
    function getSelectedColumns($prj_id, $page)
    {
        static $returns;
        
        if (empty($returns[$page])) {
            $sql = "SELECT
                        ctd_field,
                        ctd_min_role,
                        ctd_rank
                    FROM
                        " . APP_DEFAULT_DB . "." . APP_TABLE_PREFIX . "columns_to_display
                    WHERE
                        ctd_prj_id = $prj_id AND
                        ctd_page = '$page'
                    ORDER BY
                        ctd_rank";
            $res = $GLOBALS["db_api"]->dbh->getAssoc($sql, '', '', DB_FETCHMODE_ASSOC);
            if (PEAR::isError($res)) {
                Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
                return array();
            }
            $returns[$page] = array();
            foreach ($res as $field_name => $row) {
                $returns[$page][$field_name] = Display_Column::getColumnInfo($page, $field_name);
                $returns[$page][$field_name]['min_role'] = $row['ctd_min_role'];
                $returns[$page][$field_name]['rank'] = $row['ctd_rank'];
            }
        }
        return $returns[$page];
    }
    
    
    /**
     * Returns the info of the column
     * 
     * @access  public
     * @param   string $page The name of the page.
     * @param   string $column The name of the column
     * @return  string Info on the column
     */
    function getColumnInfo($page, $column)
    {
        $columns = Display_Column::getAllColumns($page);
        return $columns[$column];
    }
    
    
    /**
     * Returns all columns available for a page
     * 
     * @access  public
     * @param   string $page The name of the page
     * @return  array An array of columns
     */
    function getAllColumns($page)
    {
        
        $columns = array(
            "list_issues"   =>  array(
                    "iss_pri_id"    =>  array(
                            "title" =>  "Priority"
                    ),
                    "iss_id"    =>  array(
                            "title" =>  "Issue ID"
                    ),
                    "usr_full_name" =>  array(
                            "title" =>  "Reporter"
                    ),
                    "iss_grp_id"    =>  array(
                            "title" =>  "Group"
                    ),
                    "assigned"  =>  array(
                            "title" =>  "Assigned"
                    ),
                    "time_spent"    =>  array(
                            "title" =>  "Time Spent"
                    ),
                    "prc_title"     =>  array(
                            "title" =>  "Category"
                    ),
                    "pre_title" =>  array(
                            "title" =>  "Release"
                    ),
                    "iss_customer_id"   =>  array(
                            "title" =>  "Customer"
                    ),
                    "iss_sta_id"    =>  array(
                            "title" =>  "Status"
                    ),
                    "sta_change_date"   =>  array(
                            "title" =>  "Status Change Date"
                    ),
                    "last_action_date"  =>  array(
                            "title" =>  "Last Action Date"
                    ),
                    "custom_fields" =>  array(
                            "title" =>  "Custom Fields"
                    ),
                    "iss_summary"   =>  array(
                            "title" =>  "Summary",
                            "align" =>  "left",
                            "width" =>  '30%'
                    )
            )
        );
        
        return $columns[$page];
    }
    
    
    /**
     * Saves settings on which columns should be displayed.
     * 
     * @access  public
     * @return  integer 1 if settings were saved successfully, -1 if there was an error.
     */
    function save()
    {
        $page = $_REQUEST['page'];
        $prj_id = $_REQUEST['prj_id'];
        
        $ranks = $_REQUEST['rank'];
        asort($ranks);
        
        // delete current entries
        $sql = "DELETE FROM
                    " . APP_DEFAULT_DB . "." . APP_TABLE_PREFIX . "columns_to_display
                WHERE
                    ctd_prj_id = $prj_id AND
                    ctd_page = '$page'";
        $res = $GLOBALS["db_api"]->dbh->query($sql);
        if (PEAR::isError($res)) {
            Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
            return -1;
        }
        $rank = 1;
        foreach ($ranks as $field_name => $requested_rank) {
            $sql = "INSERT INTO
                        " . APP_DEFAULT_DB . "." . APP_TABLE_PREFIX . "columns_to_display
                    SET
                        ctd_prj_id = $prj_id,
                        ctd_page = '$page',
                        ctd_field = '$field_name',
                        ctd_min_role = " . $_REQUEST['min_role'][$field_name] . ",
                        ctd_rank = $rank";
            $res = $GLOBALS["db_api"]->dbh->query($sql);
            if (PEAR::isError($res)) {
                Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
                return -1;
            }
            $rank++;
        }
        return 1;
    }
    
    
    /**
     * Adds records in database for new project.
     * 
     * @param   integer $prj_id The ID of the project.
     */
    function setupNewProject($prj_id)
    {
        $page = 'list_issues';
        $columns = Display_Column::getAllColumns($page);
        $rank = 1;
        foreach ($columns as $field_name => $column) {
            $sql = "INSERT INTO
                        " . APP_DEFAULT_DB . "." . APP_TABLE_PREFIX . "columns_to_display
                    SET
                        ctd_prj_id = $prj_id,
                        ctd_page = '$page',
                        ctd_field = '$field_name',
                        ctd_min_role = 1,
                        ctd_rank = $rank";
            $res = $GLOBALS["db_api"]->dbh->query($sql);
            if (PEAR::isError($res)) {
                Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
                return -1;
            }
            $rank++;
        }
    }
}

// benchmarking the included file (aka setup time)
if (APP_BENCHMARK) {
    $GLOBALS['bench']->setMarker('Included Column Class');
}
?>