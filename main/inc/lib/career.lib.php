<?php
/* For licensing terms, see /license.txt */

use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Vertex;

/**
 * Class Career
 */
class Career extends Model
{
    public $table;
    public $columns = array(
        'id',
        'name',
        'description',
        'status',
        'created_at',
        'updated_at'
    );

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->table = Database::get_main_table(TABLE_CAREER);
    }

    /**
     * Get the count of elements
     * @return int
     */
    public function get_count()
    {
        $row = Database::select(
            'count(*) as count',
            $this->table,
            array(),
            'first'
        );
        return $row['count'];
    }

    /**
     * @param array $where_conditions
     * @return array
     */
    public function get_all($where_conditions = array())
    {
        return Database::select(
            '*',
            $this->table,
            array('where' => $where_conditions, 'order' => 'name ASC')
        );
    }

    /**
     * Update all promotion status by career
     * @param   int     $career_id
     * @param   int     $status (1 or 0)
     */
    public function update_all_promotion_status_by_career_id($career_id, $status)
    {
        $promotion = new Promotion();
        $promotion_list = $promotion->get_all_promotions_by_career_id($career_id);
        if (!empty($promotion_list)) {
            foreach ($promotion_list as $item) {
                $params['id'] = $item['id'];
                $params['status'] = $status;
                $promotion->update($params);
                $promotion->update_all_sessions_status_by_promotion_id($params['id'], $status);
            }
        }
    }

    /**
     * Displays the title + grid
     */
    public function display()
    {
        echo '<div class="actions" style="margin-bottom:20px">';
        echo '<a href="career_dashboard.php">'.
            Display::return_icon('back.png', get_lang('Back'), '', ICON_SIZE_MEDIUM).'</a>';
        echo '<a href="'.api_get_self().'?action=add">'.
                Display::return_icon('new_career.png', get_lang('Add'), '', ICON_SIZE_MEDIUM).'</a>';
        echo '</div>';
        echo Display::grid_html('careers');
    }

    /**
     * @return array
     */
    public function get_status_list()
    {
        return array(
            CAREER_STATUS_ACTIVE => get_lang('Unarchived'),
            CAREER_STATUS_INACTIVE => get_lang('Archived')
        );
    }

    /**
     * Returns a Form validator Obj
     * @todo the form should be auto generated
     * @param   string  $url
     * @param   string  $action add, edit
     * @return  FormValidator
     */
    public function return_form($url, $action)
    {
        $form = new FormValidator('career', 'post', $url);
        // Setting the form elements
        $header = get_lang('Add');
        if ($action == 'edit') {
            $header = get_lang('Modify');
        }

        $form->addElement('header', $header);
        $id = isset($_GET['id']) ? intval($_GET['id']) : '';
        $form->addElement('hidden', 'id', $id);
        $form->addElement('text', 'name', get_lang('Name'), array('size' => '70'));
        $form->addHtmlEditor(
            'description',
            get_lang('Description'),
            false,
            false,
            array(
                'ToolbarSet' => 'Careers',
                'Width' => '100%',
                'Height' => '250'
            )
        );
        $status_list = $this->get_status_list();
        $form->addElement('select', 'status', get_lang('Status'), $status_list);
        if ($action == 'edit') {
            $form->addElement('text', 'created_at', get_lang('CreatedAt'));
            $form->freeze('created_at');
        }
        if ($action == 'edit') {
            $form->addButtonSave(get_lang('Modify'), 'submit');
        } else {
            $form->addButtonCreate(get_lang('Add'), 'submit');
        }

        // Setting the defaults
        $defaults = $this->get($id);

        if (!empty($defaults['created_at'])) {
            $defaults['created_at'] = api_convert_and_format_date($defaults['created_at']);
        }
        if (!empty($defaults['updated_at'])) {
            $defaults['updated_at'] = api_convert_and_format_date($defaults['updated_at']);
        }
        $form->setDefaults($defaults);

        // Setting the rules
        $form->addRule('name', get_lang('ThisFieldIsRequired'), 'required');

        return $form;
    }

    /**
     * Copies the career to a new one
     * @param   integer     Career ID
     * @param   boolean     Whether or not to copy the promotions inside
     * @return  integer     New career ID on success, false on failure
     */
    public function copy($id, $copy_promotions = false)
    {
        $career = $this->get($id);
        $new = array();
        foreach ($career as $key => $val) {
            switch ($key) {
                case 'id':
                case 'updated_at':
                    break;
                case 'name':
                    $val .= ' '.get_lang('CopyLabelSuffix');
                    $new[$key] = $val;
                    break;
                case 'created_at':
                    $val = api_get_utc_datetime();
                    $new[$key] = $val;
                    break;
                default:
                    $new[$key] = $val;
                    break;
            }
        }
        $cid = $this->save($new);
        if ($copy_promotions) {
            //Now also copy each session of the promotion as a new session and register it inside the promotion
            $promotion = new Promotion();
            $promo_list = $promotion->get_all_promotions_by_career_id($id);
            if (!empty($promo_list)) {
                foreach ($promo_list as $item) {
                    $promotion->copy($item['id'], $cid, true);
                }
            }
        }

        return $cid;
    }

    /**
     * @param int $career_id
     * @return bool
     */
    public function get_status($career_id)
    {
        $TBL_CAREER = Database::get_main_table(TABLE_CAREER);
        $career_id = intval($career_id);
        $sql = "SELECT status FROM $TBL_CAREER WHERE id = '$career_id'";
        $result = Database::query($sql);
        if (Database::num_rows($result) > 0) {
            $data = Database::fetch_array($result);
            return $data['status'];
        } else {
            return false;
        }
    }

    /**
     * @param array $params
     * @param bool $show_query
     * @return bool
     */
    public function save($params, $show_query = false)
    {
        if (isset($params['description'])) {
            $params['description'] = Security::remove_XSS($params['description']);
        }

        $id = parent::save($params);
        if (!empty($id)) {
            Event::addEvent(
                LOG_CAREER_CREATE,
                LOG_CAREER_ID,
                $id,
                api_get_utc_datetime(),
                api_get_user_id()
            );
        }

        return $id;
    }

    /**
     * Delete a record from the career table and report in the default events log table
     * @param int $id The ID of the career to delete
     * @return bool True if the career could be deleted, false otherwise
     */
    public function delete($id)
    {
        $res = parent::delete($id);
        if ($res) {
            $extraFieldValues = new ExtraFieldValue('career');
            $extraFieldValues->deleteValuesByItem($id);
            Event::addEvent(
                LOG_CAREER_DELETE,
                LOG_CAREER_ID,
                $id,
                api_get_utc_datetime(),
                api_get_user_id()
            );
        }
        return $res;
    }

    /**
     * Update the career table with the given params
     * @param array $params The field values to be set
     * @return bool Returns true if the record could be updated, false otherwise
     */
    public function update($params)
    {
        if (isset($params['description'])) {
            $params['description'] = Security::remove_XSS($params['description']);
        }

        return parent::update($params);
    }

    /**
     * @param array
     * @param Graph $graph
     *
     * @return string
     */
    public static function renderDiagram($careerInfo, $graph)
    {
        if (!($graph instanceof Graph)) {
            return '';
        }

        $debug = false;

        // Getting max column
        $maxColumn = 0;
        foreach ($graph->getVertices() as $vertex) {
            $groupId = (int) $vertex->getGroup();
            if ($groupId > $maxColumn) {
                $maxColumn = $groupId;
            }
        }

        $list = [];
        /** @var Vertex $vertex */
        foreach ($graph->getVertices() as $vertex) {
            $group = $vertex->getAttribute('Group');
            $subGroup = $vertex->getAttribute('SubGroup');
            $subGroupData = explode(':', $subGroup);
            $column = $vertex->getGroup();
            $row = $vertex->getAttribute('Row');
            $subGroupId = $subGroupData[0];
            $label = isset($subGroupData[1]) ? $subGroupData[1] : '';
            $list[$group][$subGroupId]['columns'][$column][$row] = $vertex;
            $list[$group][$subGroupId]['label'] = $label;
        }

        $maxGroups = count($list);
        $widthGroup = 30;
        if (!empty($maxGroups)) {
            $widthGroup = 85 / $maxGroups;
        }

        $connections = '';
        $groupDrawLine = [];
        $groupCourseList = [];

        // Read Connections column
        foreach ($list as $group => $subGroupList) {
            foreach ($subGroupList as $subGroupData) {
                $columns = $subGroupData['columns'];
                $showGroupLine = true;
                if (count($columns) == 1) {
                    $showGroupLine = false;
                }
                $groupDrawLine[$group] = $showGroupLine;

                //if ($showGroupLine == false) {
                /** @var Vertex $vertex */
                foreach ($columns as $row => $items) {
                    foreach ($items as $vertex) {
                        if ($vertex instanceof Vertex) {
                            $groupCourseList[$group][] = $vertex->getId();
                            $connectionList = $vertex->getAttribute(
                                'Connections'
                            );
                            $firstConnection = '';
                            $secondConnection = '';
                            if (!empty($connectionList)) {
                                $explode = explode('-', $connectionList);
                                $pos = strpos($explode[0], 'SG');
                                if ($pos === false) {
                                    $pos = strpos($explode[0], 'G');
                                    if (is_numeric($pos)) {
                                        // group_123 id
                                        $groupValueId = (int)str_replace(
                                            'G',
                                            '',
                                            $explode[0]
                                        );
                                        $firstConnection = 'group_'.$groupValueId;
                                        $groupDrawLine[$groupValueId] = true;
                                    } else {
                                        // Course block (row_123 id)
                                        if (!empty($explode[0])) {
                                            $firstConnection = 'row_'.(int) $explode[0];
                                        }
                                    }
                                } else {
                                    // subgroup__123 id
                                    $firstConnection = 'subgroup_'.(int)str_replace('SG', '', $explode[0]);
                                }

                                $pos = strpos($explode[1], 'SG');
                                if ($pos === false) {
                                    $pos = strpos($explode[1], 'G');
                                    if (is_numeric($pos)) {
                                        $groupValueId = (int)str_replace(
                                            'G',
                                            '',
                                            $explode[1]
                                        );
                                        $secondConnection = 'group_'.$groupValueId;
                                        $groupDrawLine[$groupValueId] = true;
                                    } else {
                                        // Course block (row_123 id)
                                        if (!empty($explode[0])) {
                                            $secondConnection = 'row_'.(int) $explode[1];
                                        }
                                    }
                                } else {
                                    $secondConnection = 'subgroup_'.(int)str_replace('SG', '', $explode[1]);
                                }

                                if (!empty($firstConnection) && !empty($firstConnection)) {
                                    $connections .= self::createConnection(
                                        $firstConnection,
                                        $secondConnection,
                                        ['Left', 'Right']
                                    );
                                }
                            }
                        }
                    }
                }
                //}
            }
        }

        $graphHtml = '<div class="container">';
        foreach ($list as $group => $subGroupList) {
            $showGroupLine = false;
            if (isset($groupDrawLine[$group]) && $groupDrawLine[$group]) {
                $showGroupLine = true;
            }
            $graphHtml .= self::parseSubGroups(
                $groupCourseList,
                $group,
                $showGroupLine,
                $subGroupList,
                $widthGroup
            );
        }
        $graphHtml .= '</div>';
        $graphHtml .= $connections;

        return $graphHtml;
    }

    /**
     * @param array $groupCourseList list of groups and their courses
     * @param int $group
     * @param bool $showGroupLine
     * @param array $subGroupList
     * @param $widthGroup
     * @return string
     */
    public static function parseSubGroups(
        $groupCourseList,
        $group,
        $showGroupLine,
        $subGroupList,
        $widthGroup
    ) {
        $topValue = 90;
        $defaultSpace = 40;
        $leftGroup = $defaultSpace.'px';
        if ($group == 1) {
            $leftGroup = 0;
        }

        $groupIdTag = "group_$group";
        $borderLine = $showGroupLine === true ? 'border-style:solid;' : '';
        // padding:15px;
        $graphHtml = '<div id="'.$groupIdTag.'" class="career_group" style=" '.$borderLine.' padding:15px; float:left; margin-left:'.$leftGroup.'; width:'.$widthGroup.'%">';

        foreach ($subGroupList as $subGroup => $subGroupData) {
            $subGroupLabel = $subGroupData['label'];
            $columnList = $subGroupData['columns'];

            $line = '';
            if (!empty($subGroup)) {
                $line = 'border-style:solid;';
            }
            // padding:15px;
            $graphHtml .= '<div id="subgroup_'.$subGroup.'" class="career_subgroup" style="'.$line.' margin-bottom:20px; padding:15px; float:left; margin-left:0px; width:100%">';
            if (!empty($subGroupLabel)) {
                $graphHtml .= '<h3>'.$subGroupLabel.'</h3>';
            }
            foreach ($columnList as $column => $rows) {
                $leftColumn = $defaultSpace.'px';
                if ($column == 1) {
                    $leftColumn = 0;
                }
                if (count($columnList) == 1) {
                    $leftColumn = 0;
                }

                $widthColumn = 85 / count($columnList);
                $graphHtml .= '<div id="col_'.$column.'" class="career_column" style="padding:15px;float:left; margin-left:'.$leftColumn.'; width:'.$widthColumn.'%">';
                $maxRow = 0;
                foreach ($rows as $row => $vertex) {
                    if ($row > $maxRow) {
                        $maxRow = $row;
                    }
                }

                $newRowList = [];
                $defaultSubGroup = -1;
                $subGroupCountList = [];
                for ($i = 0; $i < $maxRow; $i++) {
                    /** @var Vertex $vertex */
                    $vertex = isset($rows[$i + 1]) ? $rows[$i + 1] : null;
                    if (!is_null($vertex)) {
                        $subGroup = $vertex->getAttribute('SubGroup');
                        if ($subGroup == '' || empty($subGroup)) {
                            $defaultSubGroup = 0;
                        } else {
                            $defaultSubGroup = (int)$subGroup;
                        }
                    }
                    $newRowList[$i + 1][$defaultSubGroup][] = $vertex;
                    if (!isset($subGroupCountList[$defaultSubGroup])) {
                        $subGroupCountList[$defaultSubGroup] = 1;
                    } else {
                        $subGroupCountList[$defaultSubGroup]++;
                    }
                }

                $subGroup = null;
                $subGroupAdded = [];
                /** @var Vertex $vertex */
                foreach ($newRowList as $row => $subGroupList) {
                    foreach ($subGroupList as $subGroup => $vertexList) {
                        if (!empty($subGroup) && $subGroup != -1) {
                            if (!isset($subGroupAdded[$subGroup])) {
                                $subGroupAdded[$subGroup] = 1;
                            } else {
                                $subGroupAdded[$subGroup]++;
                            }
                        }

                        foreach ($vertexList as $vertex) {
                            if (is_null($vertex)) {
                                $graphHtml .= '<div class="career_empty" style="height: 130px">';
                                $graphHtml .= '</div>';
                                continue;
                            }

                            $id = $vertex->getId();
                            $rowId = "row_$row";
                            $graphHtml .= '<div id = "row_'.$id.'" class="'.$rowId.' career_row" >';
                            $color = '';
                            if (!empty($vertex->getAttribute('DefinedColor'))) {
                                $color = $vertex->getAttribute('DefinedColor');
                            }
                            $content = $vertex->getAttribute('Notes');
                            $content .= '<div class="pull-right">['.$id.']</div>';

                            $graphHtml .= Display::panel(
                                $content,
                                $vertex->getAttribute('graphviz.label'),
                                null,
                                null,
                                null,
                                null,
                                $color
                            );
                            $graphHtml .= '</div>';

                            $arrow = $vertex->getAttribute('DrawArrowFrom');
                            $found = false;
                            if (!empty($arrow)) {
                                $pos = strpos($arrow, 'SG');
                                if ($pos === false) {
                                    $pos = strpos($arrow, 'G');
                                    if (is_numeric($pos)) {
                                        $parts = explode('G', $arrow);
                                        if (empty($parts[0]) && count($parts) == 2) {
                                            $groupArrow = $parts[1];
                                            $graphHtml .= self::createConnection(
                                                "group_$groupArrow",
                                                "row_$id",
                                                ['Left', 'Right']
                                            );
                                            $found = true;
                                        }
                                    }
                                } else {
                                    $parts = explode('SG', $arrow);
                                    if (empty($parts[0]) && count($parts) == 2) {
                                        $subGroupArrow = $parts[1];
                                        /*var_dump($subGroupArrow);
                                        var_dump(array_keys($subGroupList));*/
                                        $graphHtml .= self::createConnection(
                                            "subgroup_$subGroupArrow",
                                            "row_$id",
                                            ['Left', 'Right']
                                        );
                                        $found = true;
                                    }
                                }
                            }

                            if ($found == false) {
                                $defaultArrow = ['Left', 'Right'];
                                if (isset($groupCourseList[$group]) &&
                                    in_array($arrow, $groupCourseList[$group])
                                ) {
                                    $defaultArrow = ['Top', 'Bottom'];
                                }
                                $graphHtml .= self::createConnection(
                                    "row_$arrow",
                                    "row_$id",
                                    $defaultArrow
                                );
                            }
                        }
                    }
                }
                $graphHtml .= '</div>';
            }
            $graphHtml .= '</div>';
        }
        $graphHtml .= '</div>';

        return $graphHtml;
    }

    /**
     * @param string $source
     * @param string $target
     * @param array $anchor
     * @return string
     */
    public static function createConnection($source, $target, $anchor = [])
    {
        if (empty($anchor)) {
            // Default
            $anchor = ['Bottom', 'Right'];
        }

        $anchor = implode('","', $anchor);
        $html = '<script>

        var connectorPaintStyle = {
            strokeWidth: 2,
            stroke: "#a31ed3",
            joinstyle: "round",
            outlineStroke: "white",
            outlineWidth: 2
        },
        // .. and this is the hover style.
        connectorHoverStyle = {
            strokeWidth: 3,
            stroke: "#216477",
            outlineWidth: 5,
            outlineStroke: "white"
        },
        endpointHoverStyle = {
            fill: "#E80CAF",
            stroke: "#E80CAF"
        };
        jsPlumb.ready(function() { ';
        $html .= 'jsPlumb.connect({
            source:"'.$source.'",
            target:"'.$target.'",
            endpoint:[ "Rectangle", { width:1, height:1 }],                                        
            connector: ["Flowchart"],             
            paintStyle: connectorPaintStyle,    
            hoverPaintStyle: endpointHoverStyle,                
            anchor: ["'.$anchor.'"],
            overlays: [
                [ 
                    "Arrow", 
                    { 
                        location:1,  
                        width:11, 
                        length:11 
                    } 
                ],
            ],
        });';
        $html .= '});</script>'.PHP_EOL;

        return $html;
    }
}
