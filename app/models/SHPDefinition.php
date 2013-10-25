<?php


// PHP SHP libraries arent PSR-0 yet so we have to include them.
include_once(__DIR__ . "/../lib/ShapeFile.inc.php");
include_once(__DIR__ . "/../lib/proj4php/proj4php.php");

/**
 * Shape definition model, all processing is done based on the
 * SHP specification http://www.esri.com/library/whitepapers/pdfs/shapefile.pdf.
 * @copyright (C) 2011,2013 by OKFN Belgium vzw/asbl
 * @license AGPLv3
 * @author Jan Vansteenlandt <jan@okfn.be>
 */
class ShpDefinition extends SourceType{

    protected $table = 'shpdefinitions';

    protected $fillable = array('uri', 'epsg', 'description');

    /**
     * Relationship with the TabularColumns model.
     */
    public function tabularColumns(){
        return $this->morphMany('TabularColumns', 'tabular');
    }

    /**
     * Relationship with the Definition model.
     */
    public function definition(){
        return $this->morphOne('Definition', 'source');
    }

    /**
     * Relationship with the Geo properties model.
     */
    public function geoProperties(){
        return $this->morphMany('GeoProperty', 'source');
    }

    /**
     * Hook into the save function of Eloquent by saving the parent
     * and establishing a relation to the TabularColumns model.
     *
     * Pre-requisite: parameters have already been validated.
     */
    public function save(array $options = array()){

        // If geo properties are passed, then utilize them.
        // If they're not parse the SHP file in to search for them automatically.
        $columns = @$options['columns'];

        if(empty($columns)){
            $columns = $this->parseColumns();
        }

        $geo_properties = @$options['geo_properties'];

        if(empty($geo_properties)){
            $geo_properties = $this->parseGeoProperty();
        }

        parent::save();

        // Save the TabularColumns
        foreach($columns as $column){
            $tabular_column = new TabularColumns();
            $tabular_column->index = $column[0];
            $tabular_column->column_name = $column[1];
            $tabular_column->is_pk = 0;
            $tabular_column->column_name_alias = $column[2];
            $tabular_column->tabular_type = 'ShpDefinition';
            $tabular_column->tabular_id = $this->id;
            $tabular_column->save();
        }

        // Save the GeoProperty
        foreach($geo_properties as $geo_prop){
            $geo_property = new GeoProperty();
            $geo_property->path = $geo_prop[1];
            $geo_property->geo_property = $geo_prop[0];
            $geo_property->source_id = $this->id;
            $geo_property->source_type = 'ShpDefinition';
            $geo_property->save();
        }

        return true;
    }

    /**
     * Parse the column names out of a SHP file.
     *
     * TODO clean up this function a bit.
     */
    private function parseColumns(){

        $options = array('noparts' => false);
        $is_url = (substr($this->uri , 0, 4) == "http");
        $tmp_dir = sys_get_temp_dir();
        $columns = array();

        if ($is_url) {

            // This remains untested.
            $tmp_file = uniqid();
            file_put_contents($tmp_dir . '/' . $tmp_file . ".shp", file_get_contents(substr($this->uri, 0, strlen($this->uri) - 4) . ".shp"));
            file_put_contents($tmp_dir . '/' . $tmp_file . ".dbf", file_get_contents(substr($this->uri, 0, strlen($this->uri) - 4) . ".dbf"));
            file_put_contents($tmp_dir . '/' . $tmp_file . ".shx", file_get_contents(substr($this->uri, 0, strlen($this->uri) - 4) . ".shx"));

            // Along this file the class will use file.shx and file.dbf
            $shp = new \ShapeFile($tmp_dir . '/' . $tmp_file . ".shp", $options);
        } else {

            // along this file the class will use file.shx and file.dbf
            $shp = new \ShapeFile($this->uri, $options);
        }

        $record = $shp->getNext();

        // read meta data
        if(!$record){
            \App::abort(452, "We failed to retrieve a record from the provided shape file on uri $this->uri, make sure the corresponding dbf and shx files are at the same location.");
        }

        // Get the dBASE fields.
        $dbf_fields = $record->getDbfFields();
        $column_index = 0;

        foreach ($dbf_fields as $field) {

            $property = strtolower($field["fieldname"]);
            array_push($columns, array($column_index, $property, $property));
            $column_index++;
        }

        $shp_data = $record->getShpData();

        // Get the geographical column names.
        // Either coords will be set (identified by the parts)
        // or a lat long will be set (identified by x and y)
        if(!empty($shp_data['parts'])) {
            array_push($columns, array($column_index, 'parts', 'parts'));
        }else if(!empty($shp_data['x'])) {
            array_push($columns, array($column_index, 'x', 'x'));
            array_push($columns, array($column_index + 1, 'y', 'y'));
        }else{
            \App::abort(452, 'The shapefile could not be processed, probably because the geometry in the shape file is not supported.
                The supported geometries are Null Shape, Point, PolyLine, Polygon, MultiPoint');
        }
        return $columns;

    }


    /**
     * Parse the geo column names out of a SHP file.
     */
    private function parseGeoProperty(){

        $options = array('noparts' => false);
        $is_url = (substr($this->uri , 0, 4) == "http");
        $tmp_dir = sys_get_temp_dir();
        $geo_properties = array();

        if ($is_url) {

            // This remains untested.
            $tmp_file = uniqid();
            file_put_contents($tmp_dir . '/' . $tmp_file . ".shp", file_get_contents(substr($this->uri, 0, strlen($this->uri) - 4) . ".shp"));
            file_put_contents($tmp_dir . '/' . $tmp_file . ".dbf", file_get_contents(substr($this->uri, 0, strlen($this->uri) - 4) . ".dbf"));
            file_put_contents($tmp_dir . '/' . $tmp_file . ".shx", file_get_contents(substr($this->uri, 0, strlen($this->uri) - 4) . ".shx"));

            $shp = new \ShapeFile($tmp_dir . '/' . $tmp_file . ".shp", $options);
        } else {
            $shp = new \ShapeFile($this->uri, $options);
        }

        $record = $shp->getNext();

        // read meta data
        if(!$record){
            \App::abort(452, "We failed to retrieve a record from the provided shape file on uri $this->uri, make sure the corresponding dbf and shx files are at the same location.");
        }

        $shp_data = $record->getShpData();
        $shape_type = strtolower($record->getRecordClass());

        // Get the geographical column names.
        // Either multiple coordinates will be set (identified by the parts)
        // or a lat long pair will be set (identified by x and y).
        if(!empty($shp_data['parts'])) {
            if(strpos($shape_type, 'polyline')){
                array_push($geo_properties, array('polyline', 'parts'));
            }else if(strpos($shape_type, 'polygon')){
                array_push($geo_properties, array('polygon', 'parts'));
            }else{ // TODO support more types
                \App::abort(452, 'Provided geometric type ( $shape_type ) is not supported');
            }
        }else if(isset($shp_data['x'])){
            array_push($geo_properties, array('latitude', 'x'));
            array_push($geo_properties, array('longitude', 'y'));
        }

        return $geo_properties;
    }

    /**
     * Validate the input for this model.
     */
    public static function validate($params){

        $tabular_params = array_only($params, array_keys(TabularColumns::getCreateProperties()));
        TabularColumns::validate($tabular_params);

        $geo_params = array_only($params, array_keys(GeoProperty::getCreateProperties()));
        GeoProperty::validate($geo_params);

        return parent::validate($params);
    }

    /**
     * Retrieve the set of create parameters that make up a SHP definition.
     */
    public static function getCreateProperties(){
        return array(
                'uri' => array(
                    'required' => true,
                    'description' => 'The location of the SHP file, either a URL or a local file location.',
                    ),
                'epsg' => array(
                    'required' => false,
                    'description' => 'This parameter holds the EPSG code in which the geometric properties in the shape file are encoded.',
                    'default_value' => 4326
                    ),
                'description' => array(
                    'required' => true,
                    'description' => 'The descriptive or informational string that provides some context for you published dataset.',
                )
            );
    }

    /**
     * Retrieve the set of create parameters that make up a CSV definition.
     * Include the parameters that make up relationships with this model.
     */
    public static function getAllProperties(){
        return array_merge(self::getCreateProperties(), GeoProperty::getCreateProperties(), TabularColumns::getCreateProperties());
    }

    /**
     * Retrieve the set of validation rules for every create parameter.
     * If the parameters doesn't have any rules, it's not mentioned in the array.
     */
    public static function getCreateValidators(){
        return array(
            'uri' => 'required|uri',
            'description' => 'required',
        );
    }

    /**
     * Provide the correct geometric string for a given set of coordinates.
     * Used to provide information in the geoproperties model.
     */
    public static function processCoordinates($coords, $geo_props = array()){


    }

    /**
     * Case insensitive version of array_key_exists.
     * Returns the matching key on success, else false.
     *
     * @param string $key
     * @param array $search
     * @return string|false
     */
    private static function array_key_exists_nc($key, $search) {
        if (array_key_exists($key, $search)) {
            return $key;
        }
        if (!(is_string($key) && is_array($search) && count($search))) {
            return false;
        }
        $key = strtolower($key);
        foreach ($search as $k => $v) {
            if (strtolower($k) == $key) {
                return $k;
            }
        }
        return false;
    }
}
