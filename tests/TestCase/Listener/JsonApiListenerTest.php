<?php
namespace Crud\Test\TestCase\Listener;

use Cake\Controller\Controller;
use Cake\Core\Plugin;
use Cake\Filesystem\File;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use Crud\Event\Subject;
use Crud\Listener\JsonApiListener;
use Crud\TestSuite\TestCase;
use Crud\Test\App\Model\Entity\Country;

/**
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 */
class JsonApiListenerTest extends TestCase
{

    /**
     * fixtures property
     *
     * @var array
     */
    public $fixtures = [
        'plugin.crud.countries',
        'plugin.crud.cultures',
        'plugin.crud.currencies',
    ];

    /**
     * Make sure we are testing with expected default configuration values.
     */
    public function testDefaultConfig()
    {
        $listener = new JsonApiListener(new Controller());

        $expected = [
            'detectors' => [
                'jsonapi' => ['ext' => 'json', 'accepts' => 'application/vnd.api+json'],
            ],
            'exception' => [
                'type' => 'default',
                'class' => 'Cake\Network\Exception\BadRequestException',
                'message' => 'Unknown error',
                'code' => 0,
            ],
            'exceptionRenderer' => 'Crud\Error\JsonApiExceptionRenderer',
            'setFlash' => false,
            'withJsonApiVersion' => false,
            'meta' => false,
            'urlPrefix' => null,
            'jsonOptions' => [],
            'debugPrettyPrint' => true,
            'include' => [],
            'fieldSets' => [],
            'docValidatorAboutLinks' => false,
        ];

        $this->assertSame($expected, $listener->config());
    }

    /**
     * Test implementedEvents with API request
     *
     * @return void
     */
    public function testImplementedEvents()
    {
        $controller = $this
            ->getMockBuilder('\Cake\Controller\Controller')
            ->setMethods(['foobar'])
            ->disableOriginalConstructor()
            ->getMock();

        $controller->RequestHandler = $this->getMockBuilder('\Cake\Controller\Component\RequestHandlerComponent')
            ->setMethods(['config'])
            ->disableOriginalConstructor()
            ->getMock();

        $listener = $this
            ->getMockBuilder('\Crud\Listener\JsonApiListener')
            ->setMethods(['setupDetectors', '_controller'])
            ->disableOriginalConstructor()
            ->getMock();

        $listener
            ->expects($this->once())
            ->method('_controller')
            ->will($this->returnValue($controller));

        $result = $listener->implementedEvents();

        $expected = [
            'Crud.beforeHandle' => ['callable' => [$listener, 'beforeHandle'], 'priority' => 10],
            'Crud.setFlash' => ['callable' => [$listener, 'setFlash'], 'priority' => 5],
            'Crud.afterSave' => ['callable' => [$listener, 'afterSave'], 'priority' => 90],
            'Crud.beforeRender' => ['callable' => [$listener, 'respond'], 'priority' => 100],
            'Crud.beforeRedirect' => ['callable' => [$listener, 'respond'], 'priority' => 100]
        ];

        $this->assertSame($expected, $result);
    }

    /**
     * Test beforeHandle() method
     *
     * @return void
     */
    public function testBeforeHandle()
    {
        $controller = $this
            ->getMockBuilder('\Cake\Controller\Controller')
            ->setMethods(['_request'])
            ->disableOriginalConstructor()
            ->getMock();

        $controller->request = $this
            ->getMockBuilder('\Cake\Network\Request')
            ->setMethods(null)
            ->disableOriginalConstructor()
            ->getMock();

        $controller->request->data = [
            'data' => [
                'type' => 'dummy',
                'attributes' => [],
            ]
        ];

        $listener = $this
            ->getMockBuilder('\Crud\Listener\JsonApiListener')
            ->setMethods(['_controller', '_checkRequestMethods', '_convertJsonApiDataArray'])
            ->disableOriginalConstructor()
            ->getMock();

        $listener
            ->expects($this->any())
            ->method('_controller')
            ->will($this->returnValue($controller));

        $listener
            ->expects($this->any())
            ->method('_convertJsonApiDataArray')
            ->will($this->returnValue(true));

        $listener
            ->expects($this->any())
            ->method('_checkRequestMethods')
            ->will($this->returnValue(true));

        $listener->beforeHandle(new \Cake\Event\Event('Crud.beforeHandle'));
    }

    /**
     * respond()
     */
    public function testRespond()
    {
        $controller = $this
            ->getMockBuilder('\Cake\Controller\Controller')
            ->setMethods(null)
            ->enableOriginalConstructor()
            ->getMock();

        $controller->name = 'Countries';

        $action = $this
            ->getMockBuilder('\Crud\Action\IndexAction')
            ->disableOriginalConstructor()
            ->setMethods(['config'])
            ->getMock();
        $response = $this
            ->getMockBuilder('\Cake\Network\Response')
            ->setMethods(['statusCode'])
            ->getMock();

        $subject = $this
            ->getMockBuilder('\Crud\Event\Subject')
            ->getMock();
        $subject->success = true;

        $table = TableRegistry::get('Countries');
        $entity = $table->find()->first();
        $subject->entity = $entity;

        $event = new \Cake\Event\Event('Crud.afterSave', $subject);

        $listener = $this
            ->getMockBuilder('\Crud\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(['_controller', '_action', 'render'])
            ->getMock();
        $listener
            ->expects($this->next($listener))
            ->method('_controller')
            ->with()
            ->will($this->returnValue($controller));
        $listener
            ->expects($this->next($listener))
            ->method('_action')
            ->with()
            ->will($this->returnValue($action));
        $action
            ->expects($this->next($action))
            ->method('config')
            ->with('api.success')
            ->will($this->returnValue(['code' => 200]));
        $listener
            ->expects($this->next($listener))
            ->method('render')
            ->with($subject)
            ->will($this->returnValue($response));
        $response
            ->expects($this->next($response))
            ->method('statusCode')
            ->with(200);

        $listener->respond($event);
    }

    /**
     * Test afterSave event.
     */
    public function testAfterSave()
    {
        $listener = $this
            ->getMockBuilder('\Crud\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(['_controller', '_response'])
            ->getMock();

        $controller = $this
            ->getMockBuilder('\Cake\Controller\Controller')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $response = $this
            ->getMockBuilder('\Cake\Network\Response')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $listener
            ->expects($this->any())
            ->method('_response')
            ->will($this->returnValue($response));

        $listener
            ->expects($this->any())
            ->method('_controller')
            ->will($this->returnValue($controller));

        $event = $this
            ->getMockBuilder('\Cake\Event\Event')
            ->disableOriginalConstructor()
            ->setMethods(['subject'])
            ->getMock();

        $subject = $this
            ->getMockBuilder('\Crud\Event\Subject')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $event
            ->expects($this->any())
            ->method('subject')
            ->will($this->returnValue($subject));

        $this->setReflectionClassInstance($listener);

        // assert nothing happens if `success` is false
        $event->subject->success = false;
        $this->assertFalse($this->callProtectedMethod('afterSave', [$event], $listener));

        // assert nothing happens if `success` is true but both `created` and `id` are false
        $event->subject->success = true;
        $event->subject->created = false;
        $event->subject->id = false;
        $this->assertFalse($this->callProtectedMethod('afterSave', [$event], $listener));

        // assert success
        $table = TableRegistry::get('Countries');
        $entity = $table->find()->first();
        $subject->entity = $entity;

        $event->subject->success = true;
        $event->subject->created = true;
        $event->subject->id = false;
        $this->assertTrue($this->callProtectedMethod('afterSave', [$event], $listener));

        $event->subject->success = true;
        $event->subject->created = false;
        $event->subject->id = true;
        $this->assertTrue($this->callProtectedMethod('afterSave', [$event], $listener));
    }

    /**
     * _insertBelongsToDataIntoEventFindResult()
     *
     * @return void
     */
    public function testInsertBelongsToDataIntoEventFindResult()
    {
        $controller = $this
            ->getMockBuilder('\Cake\Controller\Controller')
            ->setMethods(null)
            ->enableOriginalConstructor()
            ->getMock();

        $controller->name = 'Countries';

        $event = $this
            ->getMockBuilder('\Cake\Event\Event')
            ->disableOriginalConstructor()
            ->setMethods(['subject'])
            ->getMock();

        $subject = $this
            ->getMockBuilder('\Crud\Event\Subject')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $event
            ->expects($this->any())
            ->method('subject')
            ->will($this->returnValue($subject));

        $listener = $this
            ->getMockBuilder('\Crud\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(['_controller'])
            ->getMock();

        $listener
            ->expects($this->any())
            ->method('_controller')
            ->will($this->returnValue($controller));

        $this->setReflectionClassInstance($listener);

        // assert related belongsTo model 'currency' is inserted
        $table = TableRegistry::get('Countries');
        $entity = $table->find()->first();
        $subject->entity = $entity;

        $this->assertArrayHasKey('name', $entity);
        $this->assertArrayNotHasKey('currency', $entity);

        $this->callProtectedMethod('_insertBelongsToDataIntoEventFindResult', [$event], $listener);

        $this->assertArrayHasKey('name', $subject->entity);
        $this->assertArrayHasKey('currency', $subject->entity);
    }

    /**
     * _removeForeignKeysFromEventData()
     *
     * @return void
     */
    public function testRemoveForeignKeysFromEventData()
    {
        $controller = $this
            ->getMockBuilder('\Cake\Controller\Controller')
            ->setMethods(null)
            ->enableOriginalConstructor()
            ->getMock();

        $controller->name = 'Countries';

        $event = $this
            ->getMockBuilder('\Cake\Event\Event')
            ->disableOriginalConstructor()
            ->setMethods(['subject'])
            ->getMock();

        $subject = $this
            ->getMockBuilder('\Crud\Event\Subject')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $event
            ->expects($this->any())
            ->method('subject')
            ->will($this->returnValue($subject));

        $listener = $this
            ->getMockBuilder('\Crud\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(['_controller'])
            ->getMock();

        $listener
            ->expects($this->any())
            ->method('_controller')
            ->will($this->returnValue($controller));

        $this->setReflectionClassInstance($listener);

        // assert foreign keys are removed from single entity
        $table = TableRegistry::get('Countries');
        $entity = $table->find()->first();
        $this->assertArrayHasKey('name', $entity);
        $this->assertArrayHasKey('currency_id', $entity);

        $subject->entity = $entity;

        $this->callProtectedMethod('_removeBelongsToForeignKeysFromEventData', [$event], $listener);

        $this->assertArrayHasKey('name', $subject->entity);
        $this->assertArrayNotHasKey('currency_id', $subject->entity);

        unset($subject->entity);

        // assert foreign keys are removed from entity collections
        $entities = $table->find()->all();
        foreach ($entities as $entity) {
            $this->assertArrayHasKey('name', $entity);
            $this->assertArrayHasKey('currency_id', $entity);
        }

        $subject->entities = $entities;

        $this->callProtectedMethod('_removeBelongsToForeignKeysFromEventData', [$event], $listener);

        foreach ($subject->entities as $entity) {
            $this->assertArrayHasKey('name', $entity);
            $this->assertArrayNotHasKey('currency_id', $entity);
        }
    }

    /**
     * _getNewResourceUrl()
     *
     * @return void
     */
    public function testGetNewResourceUrl()
    {
        $controller = $this
            ->getMockBuilder('\Cake\Controller\Controller')
            ->setMethods(null)
            ->enableOriginalConstructor()
            ->getMock();
        $controller->name = 'Countries';

        $listener = $this
            ->getMockBuilder('\Crud\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(['_controller', '_action'])
            ->getMock();

        $listener
            ->expects($this->any())
            ->method('_controller')
            ->will($this->returnValue($controller));

        $listener
            ->expects($this->any())
            ->method('_action')
            ->will($this->returnValue('add'));

        $this->setReflectionClassInstance($listener);

        $routerParameters = [
            'controller' => 'monkeys',
            'action' => 'view',
            123
        ];

        // assert Router defaults (to test against)
        $result = Router::url($routerParameters, true);
        $this->assertEquals('/monkeys/view/123', $result);

        // assert success
        $result = $this->callProtectedMethod('_getNewResourceUrl', ['monkeys', 123], $listener);
        $this->assertEquals('/monkeys/123', $result);
    }

    /**
     * Make sure render() works with find data
     *
     * @return void
     */
    public function testRenderWithResources()
    {
        $controller = $this
            ->getMockBuilder('\Cake\Controller\Controller')
            ->setMethods(null)
            ->enableOriginalConstructor()
            ->getMock();
        $controller->name = 'Countries';
        $controller->Countries = TableRegistry::get('countries');

        $listener = $this
            ->getMockBuilder('\Crud\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(['_controller', '_action'])
            ->getMock();

        $listener
            ->expects($this->any())
            ->method('_controller')
            ->will($this->returnValue($controller));

        $subject = $this
            ->getMockBuilder('\Crud\Event\Subject')
            ->getMock();
        $subject->entity = new Country();

        $listener->render($subject);
    }

    /**
     * Make sure render() works without find data
     *
     * @return void
     */
    public function testRenderWithoutResources()
    {
        $controller = $this
            ->getMockBuilder('\Cake\Controller\Controller')
            ->setMethods(null)
            ->enableOriginalConstructor()
            ->getMock();

        $listener = $this
            ->getMockBuilder('\Crud\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(['_controller', '_action'])
            ->getMock();

        $listener
            ->expects($this->any())
            ->method('_controller')
            ->will($this->returnValue($controller));

        $subject = $this
            ->getMockBuilder('\Crud\Event\Subject')
            ->getMock();

        $listener->render($subject);
    }

    /**
     * Make sure listener continues if neomerx package is installed
     *
     * @return void
     */
    public function testCheckPackageDependenciesSuccess()
    {
        $listener = $this
            ->getMockBuilder('\Crud\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $this->assertTrue(class_exists('\Neomerx\JsonApi\Encoder\Encoder'));

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_checkPackageDependencies', [], $listener);
    }

    /**
     * Make sure listener stops if neomerx package is not installed
     *
     * @expectedException \Crud\Error\Exception\CrudException
     * @expectedExceptionMessage JsonApiListener requires composer installing neomerx/json-api:^0.8.10
     */
    public function testCheckPackageDependenciesFail()
    {
        $this->markTestIncomplete(
            'Implement this test to bump coverage to 100%. Requires mocking system/php functions'
        );
    }

    /**
     * Make sure config option `urlPrefix` does not accept an array
     *
     * @expectedException \Crud\Error\Exception\CrudException
     * @expectedExceptionMessage JsonApiListener configuration option `urlPrefix` only accepts a string
     */
    public function testValidateConfigOptionUrlPrefixFailWithArray()
    {
        $listener = $this
            ->getMockBuilder('\Crud\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $listener->config([
            'urlPrefix' => ['array', 'not-accepted']
        ]);

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_validateConfigOptions', [], $listener);
    }

    /**
     * Make sure config option `withJsonApiVersion` accepts a boolean
     *
     * @return void
     */
    public function testValidateConfigOptionWithJsonApiVersionSuccessWithBoolean()
    {
        $listener = $this
            ->getMockBuilder('\Crud\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $listener->config([
            'withJsonApiVersion' => true
        ]);

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_validateConfigOptions', [], $listener);
    }

    /**
     * Make sure config option `withJsonApiVersion` accepts an array
     *
     * @return void
     */
    public function testValidateConfigOptionWithJsonApiVersionSuccessWithArray()
    {
        $listener = $this
            ->getMockBuilder('\Crud\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $listener->config([
            'withJsonApiVersion' => ['array' => 'accepted']
        ]);

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_validateConfigOptions', [], $listener);
    }

    /**
     * Make sure config option `withJsonApiVersion` does not accept a string
     *
     * @expectedException \Crud\Error\Exception\CrudException
     * @expectedExceptionMessage JsonApiListener configuration option `withJsonApiVersion` only accepts a boolean or an array
     */
    public function testValidateConfigOptionWithJsonApiVersionFailWithString()
    {
        $listener = $this
            ->getMockBuilder('\Crud\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $listener->config([
            'withJsonApiVersion' => 'string-not-accepted'
        ]);

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_validateConfigOptions', [], $listener);
    }

    /**
     * Make sure config option `meta` accepts an array
     *
     * @return void
     */
    public function testValidateConfigOptionMetaSuccessWithArray()
    {
        $listener = $this
            ->getMockBuilder('\Crud\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $listener->config([
            'meta' => ['array' => 'accepted']
        ]);

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_validateConfigOptions', [], $listener);
    }

    /**
     * Make sure config option `meta` does not accept a string
     *
     * @expectedException \Crud\Error\Exception\CrudException
     * @expectedExceptionMessage JsonApiListener configuration option `meta` only accepts an array
     */
    public function testValidateConfigOptionMetaFailWithString()
    {
        $listener = $this
            ->getMockBuilder('\Crud\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $listener->config([
            'meta' => 'string-not-accepted'
        ]);

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_validateConfigOptions', [], $listener);
    }


    /**
     * Make sure config option `include` does not accept a string
     *
     * @expectedException \Crud\Error\Exception\CrudException
     * @expectedExceptionMessage JsonApiListener configuration option `include` only accepts an array
     */
    public function testValidateConfigOptionIncludeFailWithString()
    {
        $listener = $this
            ->getMockBuilder('\Crud\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $listener->config([
            'include' => 'string-not-accepted'
        ]);

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_validateConfigOptions', [], $listener);
    }

    /**
     * Make sure config option `fieldSets` does not accept a string
     *
     * @expectedException \Crud\Error\Exception\CrudException
     * @expectedExceptionMessage JsonApiListener configuration option `fieldSets` only accepts an array
     */
    public function testValidateConfigOptionFieldSetsFailWithString()
    {
        $listener = $this
            ->getMockBuilder('\Crud\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $listener->config([
            'fieldSets' => 'string-not-accepted'
        ]);

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_validateConfigOptions', [], $listener);
    }

    /**
     * Make sure config option `jsonOptions` does not accept a string
     *
     * @expectedException \Crud\Error\Exception\CrudException
     * @expectedExceptionMessage JsonApiListener configuration option `jsonOptions` only accepts an array
     */
    public function testValidateConfigOptionJsonOptionsFailWithString()
    {
        $listener = $this
            ->getMockBuilder('\Crud\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $listener->config([
            'jsonOptions' => 'string-not-accepted'
        ]);

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_validateConfigOptions', [], $listener);
    }

    /**
     * Make sure config option `debugPrettyPrint` does not accept a string
     *
     * @expectedException \Crud\Error\Exception\CrudException
     * @expectedExceptionMessage JsonApiListener configuration option `debugPrettyPrint` only accepts a boolean
     */
    public function testValidateConfigOptionDebugPrettyPrintFailWithString()
    {
        $listener = $this
            ->getMockBuilder('\Crud\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $listener->config([
            'debugPrettyPrint' => 'string-not-accepted'
        ]);

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_validateConfigOptions', [], $listener);
    }

    /**
     * Make sure the listener accepts the correct request headers
     *
     * @return void
     */
    public function testCheckRequestMethodsSuccess()
    {
        $request = new Request();
        $request->env('HTTP_ACCEPT', 'application/vnd.api+json');
        $response = new Response();
        $controller = new Controller($request, $response);
        $listener = new JsonApiListener($controller);
        $listener->setupDetectors();

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_checkRequestMethods', [], $listener);

        $request = new Request();
        $request->env('HTTP_ACCEPT', 'application/vnd.api+json');
        $request->env('CONTENT_TYPE', 'application/vnd.api+json');
        $response = new Response();
        $controller = new Controller($request, $response);
        $listener = new JsonApiListener($controller);
        $listener->setupDetectors();

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_checkRequestMethods', [], $listener);
    }

    /**
     * Make sure the listener fails on non JSON API request Accept Type header
     *
     * @expectedException \Cake\Network\Exception\BadRequestException
     * @expectedExceptionMessage JSON API requests require the "application/vnd.api+json" Accept header
     */
    public function testCheckRequestMethodsFailAcceptHeader()
    {
        $request = new Request();
        $request->env('HTTP_ACCEPT', 'application/json');
        $response = new Response();
        $controller = new Controller($request, $response);
        $listener = new JsonApiListener($controller);
        $listener->setupDetectors();

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_checkRequestMethods', [], $listener);
    }

    /**
     * Make sure the listener fails on non JSON API request Content-Type header
     *
     * @expectedException \Cake\Network\Exception\BadRequestException
     * @expectedExceptionMessage JSON API requests with data require the "application/vnd.api+json" Content-Type header
     */
    public function testCheckRequestMethodsFailContentHeader()
    {
        $request = new Request();
        $request->env('HTTP_ACCEPT', 'application/vnd.api+json');
        $request->env('CONTENT_TYPE', 'application/json');
        $response = new Response();
        $controller = new Controller($request, $response);
        $listener = new JsonApiListener($controller);
        $listener->setupDetectors();

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_checkRequestMethods', [], $listener);
    }

    /**
     * Make sure the listener does not accept the PUT method (since the JSON
     * API spec only supports PATCH)
     *
     * @expectedException \Cake\Network\Exception\BadRequestException
     * @expectedExceptionMessage JSON API does not support the PUT method, use PATCH instead
     */
    public function testCheckRequestMethodsFailOnPutMethod()
    {
        $request = new Request();
        $request->env('HTTP_ACCEPT', 'application/vnd.api+json');
        $request->env('REQUEST_METHOD', 'PUT');
        $response = new Response();
        $controller = new Controller($request, $response);
        $listener = new JsonApiListener($controller);
        $listener->setupDetectors();

        $this->setReflectionClassInstance($listener);
        $this->callProtectedMethod('_checkRequestMethods', [], $listener);
    }

    /**
     * Make sure correct find data is returned from subject based on action
     *
     * @return void
     */
    public function testGetFindResult()
    {
        $listener = $this
            ->getMockBuilder('\Crud\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(['_controller'])
            ->getMock();

        $this->setReflectionClassInstance($listener);

        $subject = new Subject();
        $subject->entities = 'return-entities-property-from-subject-if-set';
        $result = $this->callProtectedMethod('_getFindResult', [$subject], $listener);
        $this->assertSame('return-entities-property-from-subject-if-set', $result);

        unset($subject->entities);

        $subject->entities = 'return-entity-property-from-subject-if-set';
        $result = $this->callProtectedMethod('_getFindResult', [$subject], $listener);
        $this->assertSame('return-entity-property-from-subject-if-set', $result);
    }

    /**
     * Make sure single/first entity is returned from subject based on action
     *
     * @return void
     */
    public function testGetSingleEntity()
    {
        $controller = $this
            ->getMockBuilder('\Cake\Controller\Controller')
            ->setMethods(null)
            ->enableOriginalConstructor()
            ->getMock();

        $listener = $this
            ->getMockBuilder('\Crud\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(['_controller', '_event'])
            ->getMock();

        $listener
            ->expects($this->any())
            ->method('_controller')
            ->will($this->returnValue($controller));

        $subject = $this
            ->getMockBuilder('\Crud\Event\Subject')
            ->getMock();

        $subject->entities = $this
            ->getMockBuilder('stdClass')
            ->disableOriginalConstructor()
            ->setMethods(['first'])
            ->getMock();

        $subject->entities
            ->expects($this->any())
            ->method('first')
            ->will($this->returnValue('return-first-entity-if-entities-property-is-set'));

        $this->setReflectionClassInstance($listener);
        $result = $this->callProtectedMethod('_getSingleEntity', [$subject], $listener);
        $this->assertSame('return-first-entity-if-entities-property-is-set', $result);

        unset($subject->entities);

        $subject->entity = 'return-entity-property-from-subject-if-set';
        $this->setReflectionClassInstance($listener);
        $result = $this->callProtectedMethod('_getSingleEntity', [$subject], $listener);
        $this->assertSame($subject->entity, $result);
    }

    /**
     * Make sure associations not present in the find result are stripped
     * from the AssociationCollection. In this test we will remove associated
     * model `Cultures`.
     *
     * @return void
     */
    public function testStripNonContainedAssociations()
    {
        $table = TableRegistry::get('Countries');
        $table->belongsTo('Currencies');
        $table->hasMany('Cultures');

        // make sure expected associations are there
        $associationsBefore = $table->associations();
        $this->assertNotEmpty($associationsBefore->get('currencies'));
        $this->assertNotEmpty($associationsBefore->get('cultures'));

        // make sure cultures are not present in the find result
        $query = $table->find()->contain([
            'Currencies'
        ]);
        $entity = $query->first();

        $this->assertNotEmpty($entity->currency);
        $this->assertNull($entity->cultures);

        // make sure cultures are removed from AssociationCollection
        $listener = new JsonApiListener(new Controller());
        $this->setReflectionClassInstance($listener);
        $associationsAfter = $this->callProtectedMethod('_stripNonContainedAssociations', [$table, $entity], $listener);

        $this->assertNotEmpty($associationsAfter->get('currencies'));
        $this->assertNull($associationsAfter->get('cultures'));
    }

    /**
     * Make sure we get a list of entity names for the current entity (name
     * passed as string) and all associated models.
     *
     * @return void
     */
    public function testGetEntityList()
    {
        $table = TableRegistry::get('Countries');
        $table->belongsTo('Currencies');
        $table->hasMany('Cultures');

        $associations = $table->associations();

        $this->assertNotEmpty($associations->get('currencies'));
        $this->assertNotEmpty($associations->get('cultures'));

        $listener = new JsonApiListener(new Controller());
        $this->setReflectionClassInstance($listener);
        $result = $this->callProtectedMethod('_getEntityList', ['Country', $associations], $listener);

        $expected = [
            'Country',
            'Currency',
            'Culture'
        ];

        $this->assertSame($expected, $result);
    }

    /**
     * _getIncludeList()
     *
     * @return void
     */
    public function testGetIncludeList()
    {
        $listener = $this
            ->getMockBuilder('\Crud\Listener\JsonApiListener')
            ->disableOriginalConstructor()
            ->setMethods(['_controller', '_event'])
            ->getMock();

        $this->setReflectionClassInstance($listener);

        // assert the include list is auto-generated for both belongsTo and
        // hasMany relations (if listener config option `include` is not set)
        $this->assertEmpty($listener->config('include'));

        $table = TableRegistry::get('Countries');
        $associations = $table->associations();
        $this->assertSame(['currencies', 'cultures'], $associations->keys());

        $expected = [
            'currency',
            'cultures'
        ];

        $result = $this->callProtectedMethod('_getIncludeList', [$associations], $listener);

        $this->assertSame($expected, $result);

        // assert the include list is still auto-generated if an association is
        // removed from the AssociationsCollection
        $associations->remove('cultures');
        $this->assertSame(['currencies'], $associations->keys());
        $result = $this->callProtectedMethod('_getIncludeList', [$associations], $listener);

        $this->assertSame(['currency'], $result);

        // assert user specified listener config option is returned as-is (no magic)
        $userSpecifiedIncludes = [
            'user-specified-list',
            'with',
            'associations-to-present-in-included-node'
        ];

        $listener->config('include', $userSpecifiedIncludes);
        $this->assertNotEmpty($listener->config('include'));
        $result = $this->callProtectedMethod('_getIncludeList', [$associations], $listener);

        $this->assertSame($userSpecifiedIncludes, $result);
    }

    /**
     * Test _checkRequestData() using POST method
     *
     * @return void
     */
    public function testCheckRequestDataWithPostMethod()
    {
        $controller = $this
            ->getMockBuilder('\Cake\Controller\Controller')
            ->setMethods(['_request'])
            ->disableOriginalConstructor()
            ->getMock();

        $request = $this
            ->getMockBuilder('\Cake\Network\Request')
            ->setMethods(['contentType', 'method'])
            ->disableOriginalConstructor()
            ->getMock();

        for ($x = 0; $x <= 0; $x++) {
            $request
                ->expects($this->at($x))
                ->method('contentType')
                ->will($this->returnValue(false));
        }

        for ($x = 1; $x <= 4; $x++) {
            $request
                ->expects($this->at($x))
                ->method('contentType')
                ->will($this->returnValue(true));
        }

        $request
            ->expects($this->any())
            ->method('method')
            ->will($this->returnValue('POST'));

        $controller->request = $request;

        $listener = $this
            ->getMockBuilder('\Crud\Listener\JsonApiListener')
            ->setMethods(['_controller', '_checkRequestMethods', '_convertJsonApiDataArray'])
            ->disableOriginalConstructor()
            ->getMock();

        $listener
            ->expects($this->any())
            ->method('_controller')
            ->will($this->returnValue($controller));

        $listener
            ->expects($this->any())
            ->method('_convertJsonApiDataArray')
            ->will($this->returnValue(true));

        $listener
            ->expects($this->any())
            ->method('_checkRequestMethods')
            ->will($this->returnValue(true));

        $this->setReflectionClassInstance($listener);

        // assert null if there is no Content-Type
        $this->assertNull($this->callProtectedMethod('_checkRequestData', [], $listener));

        // assert null if there is no request data
        $controller->request->data = null;
        $this->assertNull($this->callProtectedMethod('_checkRequestData', [], $listener));

        // assert POST is processed
        $controller->request->data = [
            'data' => [
                'type' => 'dummy',
                'attributes' => [],
            ]
        ];

        $this->callProtectedMethod('_checkRequestData', [], $listener);
    }

    /**
     * Test _checkRequestData() using PATCH method
     *
     * @return void
     */
    public function testCheckRequestDataWithPatchMethod()
    {
        $controller = $this
            ->getMockBuilder('\Cake\Controller\Controller')
            ->setMethods(['_request'])
            ->disableOriginalConstructor()
            ->getMock();

        $request = $this
            ->getMockBuilder('\Cake\Network\Request')
            ->setMethods(['contentType', 'method'])
            ->disableOriginalConstructor()
            ->getMock();

        for ($x = 0; $x <= 0; $x++) {
            $request
                ->expects($this->at($x))
                ->method('contentType')
                ->will($this->returnValue(false));
        }

        for ($x = 1; $x <= 4; $x++) {
            $request
                ->expects($this->at($x))
                ->method('contentType')
                ->will($this->returnValue(true));
        }

        $request
            ->expects($this->any())
            ->method('method')
            ->will($this->returnValue('PATCH'));

        $controller->request = $request;

        $listener = $this
            ->getMockBuilder('\Crud\Listener\JsonApiListener')
            ->setMethods(['_controller', '_checkRequestMethods', '_convertJsonApiDataArray'])
            ->disableOriginalConstructor()
            ->getMock();

        $listener
            ->expects($this->any())
            ->method('_controller')
            ->will($this->returnValue($controller));

        $listener
            ->expects($this->any())
            ->method('_convertJsonApiDataArray')
            ->will($this->returnValue(true));

        $listener
            ->expects($this->any())
            ->method('_checkRequestMethods')
            ->will($this->returnValue(true));

        $this->setReflectionClassInstance($listener);

        // assert null if there is no Content-Type
        $this->assertNull($this->callProtectedMethod('_checkRequestData', [], $listener));

        // assert null if there is no request data
        $controller->request->data = null;
        $this->assertNull($this->callProtectedMethod('_checkRequestData', [], $listener));

        // assert POST is processed
        $controller->request->data = [
            'data' => [
                'id' => 'f083ea0b-9e48-44a6-af45-a814127a3a70',
                'type' => 'dummy',
                'attributes' => [],
            ]
        ];

        $this->callProtectedMethod('_checkRequestData', [], $listener);
    }

    /**
     * Make sure arrays holding json_decoded JSON API data are properly
     * converted to CakePHP format.
     *
     * Make sure incoming JSON API data is transformed to CakePHP format.
     * Please note that data is already json_decoded by Crud here.
     *
     * @return void
     */
    public function testConvertJsonApiDataArray()
    {
        $listener = new JsonApiListener(new Controller());
        $this->setReflectionClassInstance($listener);

        // assert posted id attribute gets processed as expected
        $jsonApiArray = [
            'data' => [
                'id' => '123'
            ]
        ];

        $expected = [
            'id' => '123'
        ];
        $result = $this->callProtectedMethod('_convertJsonApiDocumentArray', [$jsonApiArray], $listener);
        $this->assertSame($expected, $result);

        // assert success (single entity, no relationships)
        $jsonApiFixture = new File(Plugin::path('Crud') . 'tests' . DS . 'Fixture' . DS . 'JsonApi' . DS . 'post_country_no_relationships.json');
        $jsonApiArray = json_decode($jsonApiFixture->read(), true);
        $expected = [
            'code' => 'NL',
            'name' => 'The Netherlands'
        ];
        $result = $this->callProtectedMethod('_convertJsonApiDocumentArray', [$jsonApiArray], $listener);

        $this->assertSame($expected, $result);

        // assert success (single entity, multiple relationships)
        $jsonApiFixture = new File(Plugin::path('Crud') . 'tests' . DS . 'Fixture' . DS . 'JsonApi' . DS . 'post_country_multiple_relationships.json');
        $jsonApiArray = json_decode($jsonApiFixture->read(), true);
        $expected = [
            'code' => 'NL',
            'name' => 'The Netherlands',
            'culture_id' => '2',
            'currency_id' => '3'
        ];
        $result = $this->callProtectedMethod('_convertJsonApiDocumentArray', [$jsonApiArray], $listener);

        $this->assertSame($expected, $result);
    }
}