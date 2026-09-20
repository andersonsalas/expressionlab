<?php

// phpcs:ignorefile

use ExpressionLab\Core\LanguageEngine;
use ExpressionLab\Core\Services\Graph;

class GraphTest extends WP_UnitTestCase {

	/**
	 * Graph instance.
	 *
	 * @var Graph
	 */
	private $graph;

	public function setUp(): void {
		parent::setUp();
		$this->graph = new Graph();
		LanguageEngine::get()->reset();
	}

	public function tearDown(): void {
		parent::tearDown();
		LanguageEngine::get()->reset();
	}

	public function test_constants_defined() {
		$this->assertEquals( 'countries-110m.json', Graph::COUNTRIES );
		$this->assertEquals( 'land-110m.json', Graph::LAND );
		$this->assertEquals( 'states-10m.json', Graph::USA_STATES );
		$this->assertEquals( '#3858e9', Graph::COLOR_BLUE );
		$this->assertEquals( '#e53935', Graph::COLOR_RED );
		$this->assertEquals( 'reds', Graph::SCHEME_REDS );
		$this->assertEquals( 'viridis', Graph::SCHEME_VIRIDIS );
	}

	public function test_constants_accessible_via_magic_getter_and_elscript() {
		$this->assertEquals( 'reds', $this->graph->SCHEME_REDS );
		$this->assertEquals( 'viridis', $this->graph->SCHEME_VIRIDIS );
		$this->assertEquals( '#3858e9', $this->graph->COLOR_BLUE );
		$this->assertNull( $this->graph->NON_EXISTENT_CONSTANT );

		$eval = LanguageEngine::get()->evaluate( 'Graph.SCHEME_REDS' );
		$this->assertEquals( 'reds', $eval['result'] );
	}

	public function test_render_associative_array_with_defaults() {
		$spec = array(
			'mark' => 'bar',
			'data' => array(
				'values' => array(
					array(
						'category' => 'A',
						'amount'   => 28,
					),
					array(
						'category' => 'B',
						'amount'   => 55,
					),
				),
			),
		);

		$this->graph->render( $spec );

		$visualizations = LanguageEngine::get()->get_visualizations();
		$this->assertCount( 1, $visualizations );

		$viz = $visualizations[0];
		$this->assertEquals( 'graph', $viz['type'] );
		$this->assertEquals( 'Graph', $viz['title'] );
		$this->assertEquals( Graph::DEFAULT_SCHEMA, $viz['data']['$schema'] );
		$this->assertEquals( Graph::DEFAULT_WIDTH, $viz['data']['width'] );
		$this->assertEquals( 'bar', $viz['data']['mark'] );
		$this->assertCount( 2, $viz['data']['data']['values'] );
	}

	public function test_render_valid_json_string() {
		$json = wp_json_encode(
			array(
				'mark' => 'point',
				'data' => array(
					'values' => array(
						array(
							'x' => 1,
							'y' => 2,
						),
					),
				),
			)
		);

		$this->graph->render( $json, 'JSON Chart' );

		$visualizations = LanguageEngine::get()->get_visualizations();
		$this->assertCount( 1, $visualizations );

		$viz = $visualizations[0];
		$this->assertEquals( 'graph', $viz['type'] );
		$this->assertEquals( 'JSON Chart', $viz['title'] );
		$this->assertEquals( 'point', $viz['data']['mark'] );
		$this->assertEquals( Graph::DEFAULT_SCHEMA, $viz['data']['$schema'] );
	}

	public function test_render_generic_object() {
		$obj       = new stdClass();
		$obj->mark = 'line';
		$obj->data = (object) array(
			'values' => array(
				(object) array( 'val' => 10 ),
			),
		);

		$this->graph->render( $obj, 'Object Chart' );

		$visualizations = LanguageEngine::get()->get_visualizations();
		$this->assertCount( 1, $visualizations );

		$viz = $visualizations[0];
		$this->assertEquals( 'graph', $viz['type'] );
		$this->assertEquals( 'Object Chart', $viz['title'] );
		$this->assertEquals( 'line', $viz['data']['mark'] );
	}

	public function test_render_custom_title() {
		$spec = array( 'mark' => 'bar' );

		$this->graph->render( $spec, 'Monthly Revenue' );

		$viz = LanguageEngine::get()->get_visualizations()[0];
		$this->assertEquals( 'Monthly Revenue', $viz['title'] );
	}

	public function test_render_title_inference_from_spec_title() {
		$spec = array(
			'title' => 'Inferred Title from Spec',
			'mark'  => 'bar',
		);

		$this->graph->render( $spec );

		$viz = LanguageEngine::get()->get_visualizations()[0];
		$this->assertEquals( 'Inferred Title from Spec', $viz['title'] );
	}

	public function test_render_title_inference_from_spec_description() {
		$spec = array(
			'description' => 'Inferred Description from Spec',
			'mark'        => 'circle',
		);

		$this->graph->render( $spec );

		$viz = LanguageEngine::get()->get_visualizations()[0];
		$this->assertEquals( 'Inferred Description from Spec', $viz['title'] );
	}

	public function test_render_preserves_custom_schema_and_width() {
		$custom_schema = 'https://vega.github.io/schema/vega-lite/v5.json';
		$custom_width  = 500;

		$spec = array(
			'$schema' => $custom_schema,
			'width'   => $custom_width,
			'mark'    => 'arc',
		);

		$this->graph->render( $spec );

		$viz = LanguageEngine::get()->get_visualizations()[0];
		$this->assertEquals( $custom_schema, $viz['data']['$schema'] );
		$this->assertEquals( $custom_width, $viz['data']['width'] );
	}

	public function test_render_invalid_json_string_throws_exception() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid JSON payload provided to Graph.render()' );

		$this->graph->render( '{not-valid-json:}' );
	}

	public function test_render_empty_string_throws_exception() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Graph.render() expects a non-empty Vega-Lite specification payload.' );

		$this->graph->render( '   ' );
	}

	public function test_render_empty_array_throws_exception() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Graph.render() expects a non-empty Vega-Lite specification payload.' );

		$this->graph->render( array() );
	}

	public function test_render_indexed_numeric_list_throws_exception() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Graph.render() expects an associative specification object, not an indexed list.' );

		$this->graph->render( array( 'bar', 'line' ) );
	}

	public function test_render_scalar_types_throw_exception() {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Graph.render() expects an associative array, object, or valid JSON string' );

		$this->graph->render( 12345 );
	}

	public function test_render_multiple_visualizations() {
		$this->graph->render( array( 'mark' => 'bar' ), 'Chart 1' );
		$this->graph->render( array( 'mark' => 'line' ), 'Chart 2' );

		$visualizations = LanguageEngine::get()->get_visualizations();
		$this->assertCount( 2, $visualizations );

		$this->assertEquals( 'Chart 1', $visualizations[0]['title'] );
		$this->assertEquals( 'bar', $visualizations[0]['data']['mark'] );

		$this->assertEquals( 'Chart 2', $visualizations[1]['title'] );
		$this->assertEquals( 'line', $visualizations[1]['data']['mark'] );
	}

	public function test_render_via_language_engine_evaluate() {
		$expression = "Graph.render({'mark': 'bar', 'data': {'values': [{'a': 1}]}}, 'REPL Chart')";
		$eval       = LanguageEngine::get()->evaluate( $expression );

		$visualizations = LanguageEngine::get()->get_visualizations();
		$this->assertCount( 1, $visualizations );

		$viz = $visualizations[0];
		$this->assertEquals( 'graph', $viz['type'] );
		$this->assertEquals( 'REPL Chart', $viz['title'] );
		$this->assertEquals( 'bar', $viz['data']['mark'] );
	}

	public function test_render_in_non_interactive_mode_silences_visualization() {
		LanguageEngine::get()->increase_prog_depth();

		$this->graph->render( array( 'mark' => 'bar' ) );

		$this->assertEmpty( LanguageEngine::get()->get_visualizations() );
		$this->assertCount( 1, LanguageEngine::get()->get_silenced_visualizations() );
	}

	public function test_render_with_show_form_in_prog_eval() {
		LanguageEngine::get()->evaluate( "prog[ show[ Graph.render({'mark': 'circle'}, 'Shown Chart') ] ]" );

		$visualizations = LanguageEngine::get()->get_visualizations();
		$this->assertCount( 1, $visualizations );
		$this->assertEquals( 'Shown Chart', $visualizations[0]['title'] );
	}

	public function test_bars_with_inferred_fields() {
		$data = array(
			array(
				'type'  => 'post',
				'count' => 50,
			),
			array(
				'type'  => 'page',
				'count' => 12,
			),
		);

		$this->graph->bars( $data );

		$visualizations = LanguageEngine::get()->get_visualizations();
		$this->assertCount( 1, $visualizations );

		$viz = $visualizations[0];
		$this->assertEquals( 'graph', $viz['type'] );
		$this->assertEquals( 'Bar Chart', $viz['title'] );
		$this->assertEquals( 'bar', $viz['data']['mark']['type'] );
		$this->assertEquals( 'type', $viz['data']['encoding']['x']['field'] );
		$this->assertEquals( 'count', $viz['data']['encoding']['y']['field'] );
	}

	public function test_bars_with_key_value_map() {
		$map = array(
			'Draft'     => 10,
			'Pending'   => 4,
			'Published' => 85,
		);

		$this->graph->bars( $map, array( 'title' => 'Status Counts' ) );

		$viz = LanguageEngine::get()->get_visualizations()[0];
		$this->assertEquals( 'Status Counts', $viz['title'] );
		$this->assertCount( 3, $viz['data']['data']['values'] );
		$this->assertEquals( 'Draft', $viz['data']['data']['values'][0]['category'] );
		$this->assertEquals( 10, $viz['data']['data']['values'][0]['value'] );
	}

	public function test_bars_with_horizontal_and_color() {
		$data = array(
			array(
				'role'  => 'Admin',
				'users' => 3,
				'dept'  => 'Tech',
			),
			array(
				'role'  => 'Editor',
				'users' => 10,
				'dept'  => 'Editorial',
			),
		);

		$this->graph->bars(
			$data,
			array(
				'horizontal'  => true,
				'color'       => 'dept',
				'color_value' => Graph::COLOR_BLUE,
				'title'       => 'Staff Roles',
			)
		);

		$viz = LanguageEngine::get()->get_visualizations()[0];
		$this->assertEquals( 'Staff Roles', $viz['title'] );
		// Horizontal swaps: x is quantitative, y is nominal
		$this->assertEquals( 'users', $viz['data']['encoding']['x']['field'] );
		$this->assertEquals( 'quantitative', $viz['data']['encoding']['x']['type'] );
		$this->assertEquals( 'role', $viz['data']['encoding']['y']['field'] );
		$this->assertEquals( 'dept', $viz['data']['encoding']['color']['field'] );
	}

	public function test_lines_chart_creation() {
		$data = array(
			array(
				'month'  => 'Jan',
				'visits' => 1200,
			),
			array(
				'month'  => 'Feb',
				'visits' => 1900,
			),
		);

		$this->graph->lines( $data, array( 'title' => 'Traffic' ) );

		$viz = LanguageEngine::get()->get_visualizations()[0];
		$this->assertEquals( 'Traffic', $viz['title'] );
		$this->assertEquals( 'line', $viz['data']['mark']['type'] );
		$this->assertTrue( $viz['data']['mark']['point'] );
		$this->assertEquals( 'month', $viz['data']['encoding']['x']['field'] );
		$this->assertEquals( -45, $viz['data']['encoding']['x']['axis']['labelAngle'] );
		$this->assertNull( $viz['data']['encoding']['x']['sort'] );
		$this->assertEquals( 'visits', $viz['data']['encoding']['y']['field'] );
		$this->assertEquals( array( 'value' => Graph::COLOR_BLUE ), $viz['data']['encoding']['color'] );

		// Custom color_value for single-series line chart (propagates to points).
		$this->graph->lines( $data, array( 'title' => 'Green Traffic', 'color_value' => Graph::COLOR_GREEN ) );
		$viz_green = LanguageEngine::get()->get_visualizations()[1];
		$this->assertEquals( array( 'value' => Graph::COLOR_GREEN ), $viz_green['data']['encoding']['color'] );
		$this->assertEquals( Graph::COLOR_GREEN, $viz_green['data']['mark']['color'] );

		// Multi-series line chart with categorical color grouping.
		$multi_data = array(
			array(
				'month'   => 'Jan',
				'visits'  => 1200,
				'channel' => 'Organic',
			),
			array(
				'month'   => 'Feb',
				'visits'  => 1900,
				'channel' => 'Organic',
			),
			array(
				'month'   => 'Jan',
				'visits'  => 800,
				'channel' => 'Direct',
			),
			array(
				'month'   => 'Feb',
				'visits'  => 950,
				'channel' => 'Direct',
			),
		);
		$this->graph->lines( $multi_data, array( 'title' => 'Multi Traffic', 'color' => 'channel' ) );
		$viz2 = LanguageEngine::get()->get_visualizations()[2];
		$this->assertEquals( 'channel', $viz2['data']['encoding']['color']['field'] );
		$this->assertEquals( 'nominal', $viz2['data']['encoding']['color']['type'] );

		// Multi-series line chart with numeric time and metric fields.
		$server_data = array(
			array(
				'hour'   => 1,
				'metric' => 45,
				'server' => 'web-01',
			),
			array(
				'hour'   => 2,
				'metric' => 52,
				'server' => 'web-01',
			),
			array(
				'hour'   => 1,
				'metric' => 38,
				'server' => 'web-02',
			),
			array(
				'hour'   => 2,
				'metric' => 48,
				'server' => 'web-02',
			),
		);
		$this->graph->lines( $server_data, array( 'title' => 'Server Load', 'color' => 'server' ) );
		$viz3 = LanguageEngine::get()->get_visualizations()[3];
		$this->assertEquals( 'hour', $viz3['data']['encoding']['x']['field'] );
		$this->assertEquals( 'metric', $viz3['data']['encoding']['y']['field'] );
		$this->assertEquals( 'server', $viz3['data']['encoding']['color']['field'] );
	}

	public function test_pie_and_donut_chart_creation() {
		$data = array(
			array(
				'role'  => 'Admin',
				'count' => 5,
			),
			array(
				'role'  => 'User',
				'count' => 95,
			),
		);

		// Test standard pie
		$this->graph->pie( $data, array( 'title' => 'User Distribution' ) );

		$viz1 = LanguageEngine::get()->get_visualizations()[0];
		$this->assertEquals( 'User Distribution', $viz1['title'] );
		$this->assertEquals( 'arc', $viz1['data']['mark']['type'] );
		$this->assertEquals( 0, $viz1['data']['mark']['innerRadius'] );

		LanguageEngine::get()->reset();

		// Test donut
		$this->graph->pie(
			$data,
			array(
				'donut'        => true,
				'inner_radius' => 70,
			)
		);

		$viz2 = LanguageEngine::get()->get_visualizations()[0];
		$this->assertEquals( 'Donut Chart', $viz2['title'] );
		$this->assertEquals( 70, $viz2['data']['mark']['innerRadius'] );
		$this->assertEquals( 'count', $viz2['data']['encoding']['theta']['field'] );
		$this->assertEquals( 'role', $viz2['data']['encoding']['color']['field'] );
	}

	public function test_worldmap_generates_two_layers_with_defaults() {
		$data = array(
			array(
				'id'      => 643,
				'country' => 'Russia',
				'spam'    => 3420,
			),
			array(
				'id'      => 840,
				'country' => 'USA',
				'spam'    => 1930,
			),
		);

		$this->graph->worldmap( $data, array( 'title' => 'Global Threat Map' ) );

		$viz = LanguageEngine::get()->get_visualizations()[0];
		$this->assertEquals( 'Global Threat Map', $viz['title'] );
		$this->assertEquals( 'equalEarth', $viz['data']['projection']['type'] );

		$this->assertArrayHasKey( 'layer', $viz['data'] );
		$this->assertCount( 2, $viz['data']['layer'] );

		// Layer 0: Base map
		$base_layer = $viz['data']['layer'][0];
		$this->assertEquals( Graph::COUNTRIES, $base_layer['data']['url'] );
		$this->assertEquals( '#eef0f3', $base_layer['mark']['fill'] );

		// Layer 1: Data choropleth
		$data_layer = $viz['data']['layer'][1];
		$this->assertEquals( Graph::COUNTRIES, $data_layer['data']['url'] );
		$this->assertEquals( 'spam', $data_layer['encoding']['color']['field'] );
		$this->assertEquals( Graph::SCHEME_BLUES, $data_layer['encoding']['color']['scale']['scheme'] );
		$this->assertEquals( '#000000', $data_layer['mark']['stroke'] );
		$this->assertEquals( 0.5, $data_layer['mark']['strokeWidth'] );
		$this->assertCount( 3, $data_layer['encoding']['tooltip'] );
		$this->assertEquals( 'country_name', $data_layer['encoding']['tooltip'][0]['field'] );
		$this->assertEquals( 'country_code', $data_layer['encoding']['tooltip'][1]['field'] );
		$this->assertEquals( 'spam', $data_layer['encoding']['tooltip'][2]['field'] );
	}

	/**
	 * Helper to invoke private Graph::resolve_country via Reflection.
	 *
	 * @param mixed $val Country code, ID, or name.
	 * @return array|null
	 */
	private function resolve_country( $val ): ?array {
		$ref    = new \ReflectionClass( $this->graph );
		$method = $ref->getMethod( 'resolve_country' );
		return $method->invoke( $this->graph, $val );
	}

	public function test_resolve_country_various_formats() {
		// Alpha-2 code.
		$us = $this->resolve_country( 'US' );
		$this->assertNotNull( $us );
		$this->assertEquals( '840', $us['id'] );
		$this->assertEquals( 'United States', $us['name'] );
		$this->assertEquals( 'US', $us['alpha2'] );
		$this->assertEquals( 'USA', $us['alpha3'] );

		// Lowercase Alpha-2 code.
		$es = $this->resolve_country( 'es' );
		$this->assertNotNull( $es );
		$this->assertEquals( '724', $es['id'] );
		$this->assertEquals( 'Spain', $es['name'] );

		// Alpha-3 code.
		$ru = $this->resolve_country( 'RUS' );
		$this->assertNotNull( $ru );
		$this->assertEquals( '643', $ru['id'] );
		$this->assertEquals( 'Russia', $ru['name'] );

		// Numeric ID (integer and string with leading zero).
		$ar = $this->resolve_country( 32 );
		$this->assertNotNull( $ar );
		$this->assertEquals( '032', $ar['id'] );
		$this->assertEquals( 'Argentina', $ar['name'] );

		$ar_str = $this->resolve_country( '032' );
		$this->assertNotNull( $ar_str );
		$this->assertEquals( '032', $ar_str['id'] );

		// Common country names and aliases.
		$uk = $this->resolve_country( 'UK' );
		$this->assertNotNull( $uk );
		$this->assertEquals( '826', $uk['id'] );
		$this->assertEquals( 'United Kingdom', $uk['name'] );

		$kr = $this->resolve_country( 'South Korea' );
		$this->assertNotNull( $kr );
		$this->assertEquals( '410', $kr['id'] );
		$this->assertEquals( 'South Korea', $kr['name'] );

		$russia = $this->resolve_country( 'Russian Federation' );
		$this->assertNotNull( $russia );
		$this->assertEquals( '643', $russia['id'] );

		// Invalid inputs.
		$this->assertNull( $this->resolve_country( null ) );
		$this->assertNull( $this->resolve_country( '' ) );
		$this->assertNull( $this->resolve_country( 'XYZ' ) );
		$this->assertNull( $this->resolve_country( 9999 ) );
	}

	public function test_worldmap_with_alpha2_codes() {
		$data = array(
			array(
				'country' => 'US',
				'spam'    => 1930,
			),
			array(
				'country' => 'ES',
				'spam'    => 420,
			),
			array(
				'country' => 'RU',
				'spam'    => 3420,
			),
		);

		$this->graph->worldmap( $data, array( 'title' => 'Spam by Country' ) );

		$viz        = LanguageEngine::get()->get_visualizations()[0];
		$data_layer = $viz['data']['layer'][1];
		$normalized = $data_layer['transform'][0]['from']['data']['values'];

		$this->assertCount( 3, $normalized );
		$this->assertEquals( '840', $normalized[0]['id'] );
		$this->assertEquals( 'United States', $normalized[0]['country_name'] );
		$this->assertEquals( 'US', $normalized[0]['country_code'] );

		$this->assertEquals( '724', $normalized[1]['id'] );
		$this->assertEquals( 'Spain', $normalized[1]['country_name'] );

		$this->assertEquals( '643', $normalized[2]['id'] );
		$this->assertEquals( 'Russia', $normalized[2]['country_name'] );

		$this->assertEquals( 'id', $data_layer['transform'][0]['from']['key'] );
		$this->assertEquals( 'id', $data_layer['transform'][0]['lookup'] );
	}

	public function test_worldmap_with_associative_map() {
		$data = array(
			'US' => 1930,
			'ES' => 420,
			'RU' => 3420,
		);

		$this->graph->worldmap( $data, array( 'title' => 'Spam Frequencies' ) );

		$viz        = LanguageEngine::get()->get_visualizations()[0];
		$data_layer = $viz['data']['layer'][1];
		$normalized = $data_layer['transform'][0]['from']['data']['values'];

		$this->assertCount( 3, $normalized );
		$this->assertEquals( '840', $normalized[0]['id'] );
		$this->assertEquals( 'United States', $normalized[0]['country_name'] );
		$this->assertEquals( 1930, $normalized[0]['value'] );
		$this->assertEquals( 'value', $data_layer['encoding']['color']['field'] );
	}

	public function test_boxplot_chart_creation() {
		$data = array(
			array(
				'group' => 'A',
				'score' => 85,
			),
			array(
				'group' => 'A',
				'score' => 90,
			),
			array(
				'group' => 'B',
				'score' => 70,
			),
		);

		$this->graph->boxplot( $data, array( 'title' => 'Scores' ) );

		$viz = LanguageEngine::get()->get_visualizations()[0];
		$this->assertEquals( 'Scores', $viz['title'] );
		$this->assertEquals( 'boxplot', $viz['data']['mark']['type'] );
		$this->assertEquals( 'group', $viz['data']['encoding']['x']['field'] );
		$this->assertEquals( -45, $viz['data']['encoding']['x']['axis']['labelAngle'] );
		$this->assertEquals( 'score', $viz['data']['encoding']['y']['field'] );
		$this->assertEquals( array( 'fill' => Graph::COLOR_BLUE ), $viz['data']['mark']['box'] );
		$this->assertEquals( array( 'color' => 'black' ), $viz['data']['mark']['rule'] );
	}

	public function test_helpers_throw_on_empty_data() {
		$this->expectException( \InvalidArgumentException::class );
		$this->graph->bars( array() );
	}

	public function test_helpers_via_language_engine_evaluate() {
		$expr = "Graph.bars({'Posts': 100, 'Pages': 20}, {'title': 'Content Summary'})";
		LanguageEngine::get()->evaluate( $expr );

		$visualizations = LanguageEngine::get()->get_visualizations();
		$this->assertCount( 1, $visualizations );
		$this->assertEquals( 'Content Summary', $visualizations[0]['title'] );
	}

	public function test_usa_map_with_state_codes() {
		$data = array(
			array(
				'state' => 'CA',
				'sales' => 500,
			),
			array(
				'state' => 'TX',
				'sales' => 350,
			),
			array(
				'state' => 'NY',
				'sales' => 420,
			),
		);

		$this->graph->usa( $data, array( 'title' => 'US Sales' ) );

		$viz = LanguageEngine::get()->get_visualizations()[0];
		$this->assertEquals( 'US Sales', $viz['title'] );
		$this->assertEquals( 'albersUsa', $viz['data']['projection']['type'] );
		$this->assertCount( 2, $viz['data']['layer'] );

		// Base layer
		$base_layer = $viz['data']['layer'][0];
		$this->assertEquals( Graph::USA_STATES, $base_layer['data']['url'] );
		$this->assertEquals( 'states', $base_layer['data']['format']['feature'] );
		$this->assertEquals( '#eef0f3', $base_layer['mark']['fill'] );

		// Data layer
		$data_layer = $viz['data']['layer'][1];
		$this->assertEquals( Graph::USA_STATES, $data_layer['data']['url'] );
		$this->assertEquals( 'states', $data_layer['data']['format']['feature'] );
		$this->assertEquals( 'sales', $data_layer['encoding']['color']['field'] );
		$this->assertEquals( Graph::SCHEME_BLUES, $data_layer['encoding']['color']['scale']['scheme'] );
		$this->assertEquals( '#000000', $data_layer['mark']['stroke'] );
		$this->assertEquals( 0.5, $data_layer['mark']['strokeWidth'] );

		// Check normalized lookup records
		$lookup = $data_layer['transform'][0];
		$this->assertEquals( 'id', $lookup['lookup'] );
		$this->assertEquals( 'id', $lookup['from']['key'] );
		$values = $lookup['from']['data']['values'];
		$this->assertCount( 3, $values );

		// CA should have FIPS 06, state_name California, state_code CA
		$this->assertEquals( '06', $values[0]['id'] );
		$this->assertEquals( 'California', $values[0]['state_name'] );
		$this->assertEquals( 'CA', $values[0]['state_code'] );

		// TX should have FIPS 48, state_name Texas, state_code TX
		$this->assertEquals( '48', $values[1]['id'] );
		$this->assertEquals( 'Texas', $values[1]['state_name'] );
		$this->assertEquals( 'TX', $values[1]['state_code'] );
	}

	public function test_usa_map_with_fips_ids() {
		$data = array(
			array(
				'fips'  => 6,
				'users' => 1200,
			),
			array(
				'fips'  => 48,
				'users' => 950,
			),
		);

		$this->graph->usa( $data );

		$viz    = LanguageEngine::get()->get_visualizations()[0];
		$values = $viz['data']['layer'][1]['transform'][0]['from']['data']['values'];

		$this->assertEquals( '06', $values[0]['id'] );
		$this->assertEquals( 'California', $values[0]['state_name'] );
		$this->assertEquals( 'CA', $values[0]['state_code'] );

		$this->assertEquals( '48', $values[1]['id'] );
		$this->assertEquals( 'Texas', $values[1]['state_name'] );
		$this->assertEquals( 'TX', $values[1]['state_code'] );
	}

	public function test_usa_map_with_state_names() {
		$data = array(
			array(
				'state' => 'Florida',
				'temp'  => 85,
			),
		);

		$this->graph->usa( $data );

		$viz    = LanguageEngine::get()->get_visualizations()[0];
		$values = $viz['data']['layer'][1]['transform'][0]['from']['data']['values'];

		$this->assertEquals( '12', $values[0]['id'] );
		$this->assertEquals( 'Florida', $values[0]['state_name'] );
		$this->assertEquals( 'FL', $values[0]['state_code'] );
	}

	public function test_usa_via_language_engine_evaluate() {
		$expr = "Graph.usa({'CA': 150, 'TX': 90}, {'title': 'Traffic by State'})";
		LanguageEngine::get()->evaluate( $expr );

		$visualizations = LanguageEngine::get()->get_visualizations();
		$this->assertCount( 1, $visualizations );
		$this->assertEquals( 'Traffic by State', $visualizations[0]['title'] );
	}

	public function test_scatter_plot_creation() {
		$data = array(
			array(
				'latency' => 15.2,
				'memory'  => 4.5,
				'module'  => 'Core',
			),
			array(
				'latency' => 88.0,
				'memory'  => 18.2,
				'module'  => 'WooCommerce',
			),
		);

		$this->graph->scatter( $data, array( 'title' => 'Latency vs Memory' ) );

		$viz = LanguageEngine::get()->get_visualizations()[0];
		$this->assertEquals( 'Latency vs Memory', $viz['title'] );
		$this->assertEquals( 'point', $viz['data']['mark']['type'] );
		$this->assertTrue( $viz['data']['mark']['filled'] );
		$this->assertEquals( 'latency', $viz['data']['encoding']['x']['field'] );
		$this->assertEquals( 'memory', $viz['data']['encoding']['y']['field'] );
		$this->assertEquals( 'module', $viz['data']['encoding']['color']['field'] );
	}

	public function test_scatter_plot_with_bubble_size() {
		$data = array(
			array(
				'x'      => 10,
				'y'      => 20,
				'weight' => 100,
			),
			array(
				'x'      => 25,
				'y'      => 50,
				'weight' => 250,
			),
		);

		$this->graph->scatter(
			$data,
			array(
				'size'  => 'weight',
				'color' => Graph::COLOR_RED,
			)
		);

		$viz = LanguageEngine::get()->get_visualizations()[0];
		$this->assertEquals( 'weight', $viz['data']['encoding']['size']['field'] );
		$this->assertEquals( Graph::COLOR_RED, $viz['data']['mark']['color'] );
	}

	public function test_heatmap_creation() {
		$data = array(
			array(
				'day'   => 'Mon',
				'hour'  => 0,
				'count' => 12,
			),
			array(
				'day'   => 'Mon',
				'hour'  => 1,
				'count' => 4,
			),
			array(
				'day'   => 'Tue',
				'hour'  => 0,
				'count' => 35,
			),
		);

		$this->graph->heatmap( $data, array( 'title' => 'Activity Heatmap' ) );

		$viz = LanguageEngine::get()->get_visualizations()[0];
		$this->assertEquals( 'Activity Heatmap', $viz['title'] );
		$this->assertEquals( 'rect', $viz['data']['mark']['type'] );
		$this->assertEquals( 'hour', $viz['data']['encoding']['x']['field'] );
		$this->assertEquals( 'day', $viz['data']['encoding']['y']['field'] );
		$this->assertEquals( 'count', $viz['data']['encoding']['color']['field'] );
		$this->assertEquals( Graph::SCHEME_BLUES, $viz['data']['encoding']['color']['scale']['scheme'] );
	}

	public function test_histogram_from_flat_numeric_array() {
		$numbers = array( 10.5, 12.0, 12.5, 14.2, 19.8, 25.0, 31.2 );

		$this->graph->histogram( $numbers, array( 'title' => 'Response Times' ) );

		$viz = LanguageEngine::get()->get_visualizations()[0];
		$this->assertEquals( 'Response Times', $viz['title'] );
		$this->assertEquals( 'bar', $viz['data']['mark']['type'] );
		$this->assertEquals( 'value', $viz['data']['encoding']['x']['field'] );
		$this->assertArrayHasKey( 'bin', $viz['data']['encoding']['x'] );
		$this->assertEquals( 'count', $viz['data']['encoding']['y']['aggregate'] );
	}

	public function test_histogram_from_records_with_options() {
		$data = array(
			array(
				'ttfb'   => 45,
				'status' => '200',
			),
			array(
				'ttfb'   => 120,
				'status' => '200',
			),
			array(
				'ttfb'   => 450,
				'status' => '500',
			),
		);

		$this->graph->histogram(
			$data,
			array(
				'field'   => 'ttfb',
				'color'   => 'status',
				'maxbins' => 15,
			)
		);

		$viz = LanguageEngine::get()->get_visualizations()[0];
		$this->assertEquals( 'ttfb', $viz['data']['encoding']['x']['field'] );
		$this->assertEquals( 15, $viz['data']['encoding']['x']['bin']['maxbins'] );
		$this->assertEquals( 'status', $viz['data']['encoding']['color']['field'] );
	}

	public function test_area_chart_creation() {
		$data = array(
			array(
				'date'      => '2026-09-01',
				'bandwidth' => 120,
			),
			array(
				'date'      => '2026-09-02',
				'bandwidth' => 180,
			),
		);

		$this->graph->area( $data, array( 'title' => 'Bandwidth Usage' ) );

		$viz = LanguageEngine::get()->get_visualizations()[0];
		$this->assertEquals( 'Bandwidth Usage', $viz['title'] );
		$this->assertEquals( 'area', $viz['data']['mark']['type'] );
		$this->assertTrue( $viz['data']['mark']['line'] );
		$this->assertEquals( 'date', $viz['data']['encoding']['x']['field'] );
		$this->assertEquals( -45, $viz['data']['encoding']['x']['axis']['labelAngle'] );
		$this->assertEquals( 'bandwidth', $viz['data']['encoding']['y']['field'] );
	}

	public function test_area_streamgraph_and_normalized() {
		$data = array(
			array(
				'month' => 'Jan',
				'users' => 45,
				'tier'  => 'Free',
			),
			array(
				'month' => 'Jan',
				'users' => 10,
				'tier'  => 'Pro',
			),
		);

		$this->graph->area(
			$data,
			array(
				'color'  => 'tier',
				'stream' => true,
			)
		);

		$viz = LanguageEngine::get()->get_visualizations()[0];
		$this->assertEquals( 'center', $viz['data']['encoding']['y']['stack'] );
		$this->assertNull( $viz['data']['encoding']['y']['axis'] );
		$this->assertEquals( 'basis', $viz['data']['mark']['interpolate'] );
	}

	public function test_timeline_interval_chart() {
		$data = array(
			array(
				'task'   => 'DB Init',
				'start'  => 0,
				'end'    => 15,
				'status' => 'OK',
			),
			array(
				'task'   => 'Plugin Load',
				'start'  => 15,
				'end'    => 75,
				'status' => 'OK',
			),
		);

		$this->graph->timeline( $data, array( 'title' => 'Boot Trace' ) );

		$viz = LanguageEngine::get()->get_visualizations()[0];
		$this->assertEquals( 'Boot Trace', $viz['title'] );
		$this->assertEquals( 'bar', $viz['data']['mark']['type'] );
		$this->assertEquals( 4, $viz['data']['mark']['cornerRadius'] );
		$this->assertEquals( 'task', $viz['data']['encoding']['y']['field'] );
		$this->assertEquals( 'start', $viz['data']['encoding']['x']['field'] );
		$this->assertEquals( 'end', $viz['data']['encoding']['x2']['field'] );
	}

	public function test_magic_getter_rejects_invalid_names() {
		$g = new Graph();
		$this->assertNull( $g->{'__proto__'} );
		$this->assertNull( $g->{'constructor'} );
		$this->assertNull( $g->{"null\x00byte"} );
		$this->assertNull( $g->{'ExpressionLab\\Core\\LanguageEngine::class'} );
		$this->assertNull( $g->{'lowercase'} );
	}

	public function test_worldmap_sanitizes_field_names_in_filter_expressions() {
		$data = array(
			array(
				'country'                => 'US',
				"val'])||true||datum['x" => 100,
			),
		);
		$this->graph->worldmap( $data, array( 'value' => "val'])||true||datum['x" ) );
		$viz    = LanguageEngine::get()->get_visualizations()[0];
		$filter = $viz['data']['layer'][1]['transform'][1]['filter'];
		$this->assertStringNotContainsString( "')", $filter );
		$this->assertMatchesRegularExpression( '/^isValid\(datum\[\'[a-zA-Z0-9_]+\'\]\)$/', $filter );
	}

	public function test_render_deeply_nested_json_throws_gracefully() {
		$nested = str_repeat( '{"a":', 600 ) . '"val"' . str_repeat( '}', 600 );
		$this->expectException( \InvalidArgumentException::class );
		$this->graph->render( $nested );
	}
}

