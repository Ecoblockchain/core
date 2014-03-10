<?php

use tdt\core\definitions\DefinitionController;
use tdt\core\datasets\DatasetController;
use Symfony\Component\HttpFoundation\Request;

class ShpTest extends TestCase
{

    // This array holds the names of the files that can be used
    // to test the shp definitions.
    private $test_data = array(
        array(
            'name' => 'boundaries',
            'file' => 'gis.osm_boundaries_v06',
            'epsg' => 4326
        ),
    );

    public function test_put_api()
    {

        // Publish each shp file in the test shp data folder.
        foreach ($this->test_data as $entry) {

            $name = $entry['name'];
            $file = $entry['file'];

            // Set the definition parameters.
            $data = array(
                'description' => "A shp publication from the $file shp file.",
                'epsg' => '4326',
                'uri' => __DIR__ . "/../data/shp/$file.shp",
                'type' => 'shp'
                );

            // Set the headers.
            $headers = array(
                'Content-Type' => 'application/tdt.definition+json'
            );

            $this->updateRequest('PUT', $headers, $data);

            // Put the definition controller to the test!
            $controller = \App::make('tdt\core\definitions\DefinitionController');
            $response = $controller->handle("shp/$name");

            // Check if the creation of the definition succeeded.
            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    public function test_get_api()
    {

        // Request the data for each of the test shp files.
        foreach ($this->test_data as $entry) {

            $name = $entry['name'];

            $uri = 'shp/'. $name .'.json';
            $this->updateRequest('GET');

            $controller = \App::make('tdt\core\datasets\DatasetController');

            $response = $controller->handle($uri);
            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    public function test_update_api()
    {
        foreach ($this->test_data as $entry) {

            $file = $entry['name'];

            $updated_description = 'An updated description for ' . $file;

            $identifier = 'shp/' . $file;

            // Set the fields that we're going to update
            $data = array(
                'description' => 'An updated description',
            );

            // Set the correct headers
            $headers = array('Content-Type' => 'application/tdt.definition+json');

            $this->updateRequest('PATCH', $headers, $data);

            // Test the patch function on the definition controller
            $controller = \App::make('tdt\core\definitions\DefinitionController');

            $response = $controller->handle($identifier);
            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    public function test_delete_api()
    {
        // Delete the published definition for each test shp file.
        foreach ($this->test_data as $entry) {

            $name = $entry['name'];

            $this->updateRequest('DELETE');

            $controller = \App::make('tdt\core\definitions\DefinitionController');

            $response = $controller->handle("shp/$name");
            $this->assertEquals(200, $response->getStatusCode());
        }

        // Check if everything is deleted properly.
        $definitions_count = Definition::all()->count();
        $shp_count = ShpDefinition::all()->count();

        $this->assertTrue($shp_count == 0);
        $this->assertTrue($definitions_count == 0);
    }
}