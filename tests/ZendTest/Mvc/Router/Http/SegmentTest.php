<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Mvc
 */

namespace ZendTest\Mvc\Router\Http;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Http\Request;
use Zend\Stdlib\Request as BaseRequest;
use Zend\Mvc\Router\Http\Segment;
use ZendTest\Mvc\Router\FactoryTester;

class SegmentTest extends TestCase
{
    public static function routeProvider()
    {
        return array(
            'simple-match' => array(
                new Segment('/:foo'),
                '/bar',
                null,
                array('foo' => 'bar')
            ),
            'no-match-without-leading-slash' => array(
                new Segment(':foo'),
                '/bar/',
                null,
                null
            ),
            'no-match-with-trailing-slash' => array(
                new Segment('/:foo'),
                '/bar/',
                null,
                null
            ),
            'offset-skips-beginning' => array(
                new Segment(':foo'),
                '/bar',
                1,
                array('foo' => 'bar')
            ),
            'offset-enables-partial-matching' => array(
                new Segment('/:foo'),
                '/bar/baz',
                0,
                array('foo' => 'bar')
            ),
            'match-overrides-default' => array(
                new Segment('/:foo', array(), array('foo' => 'baz')),
                '/bar',
                null,
                array('foo' => 'bar')
            ),
            'constraints-prevent-match' => array(
                new Segment('/:foo', array('foo' => '\d+')),
                '/bar',
                null,
                null
            ),
            'constraints-allow-match' => array(
                new Segment('/:foo', array('foo' => '\d+')),
                '/123',
                null,
                array('foo' => '123')
            ),
            'constraints-override-non-standard-delimiter' => array(
                new Segment('/:foo{-}/bar', array('foo' => '[^/]+')),
                '/foo-bar/bar',
                null,
                array('foo' => 'foo-bar')
            ),
            'constraints-with-parantheses-dont-break-parameter-map' => array(
                new Segment('/:foo/:bar', array('foo' => '(bar)')),
                '/bar/baz',
                null,
                array('foo' => 'bar', 'bar' => 'baz')
            ),
            'simple-match-with-optional-parameter' => array(
                new Segment('/[:foo]', array(), array('foo' => 'bar')),
                '/',
                null,
                array('foo' => 'bar')
            ),
            'optional-parameter-is-ignored' => array(
                new Segment('/:foo[/:bar]'),
                '/bar',
                null,
                array('foo' => 'bar')
            ),
            'optional-parameter-is-provided-with-default' => array(
                new Segment('/:foo[/:bar]', array(), array('bar' => 'baz')),
                '/bar',
                null,
                array('foo' => 'bar', 'bar' => 'baz')
            ),
            'optional-parameter-is-consumed' => array(
                new Segment('/:foo[/:bar]'),
                '/bar/baz',
                null,
                array('foo' => 'bar', 'bar' => 'baz')
            ),
            'optional-group-is-discared-with-missing-parameter' => array(
                new Segment('/:foo[/:bar/:baz]', array(), array('bar' => 'baz')),
                '/bar',
                null,
                array('foo' => 'bar', 'bar' => 'baz')
            ),
            'optional-group-within-optional-group-is-ignored' => array(
                new Segment('/:foo[/:bar[/:baz]]', array(), array('bar' => 'baz', 'baz' => 'bat')),
                '/bar',
                null,
                array('foo' => 'bar', 'bar' => 'baz', 'baz' => 'bat')
            ),
            'non-standard-delimiter-before-parameter' => array(
                new Segment('/foo-:bar'),
                '/foo-baz',
                null,
                array('bar' => 'baz')
            ),
            'non-standard-delimiter-between-parameters' => array(
                new Segment('/:foo{-}-:bar'),
                '/bar-baz',
                null,
                array('foo' => 'bar', 'bar' => 'baz')
            ),
            'non-standard-delimiter-before-optional-parameter' => array(
                new Segment('/:foo{-/}[-:bar]/:baz'),
                '/bar-baz/bat',
                null,
                array('foo' => 'bar', 'bar' => 'baz', 'baz' => 'bat')
            ),
            'non-standard-delimiter-before-ignored-optional-parameter' => array(
                new Segment('/:foo{-/}[-:bar]/:baz'),
                '/bar/bat',
                null,
                array('foo' => 'bar', 'baz' => 'bat')
            ),
            'parameter-with-dash-in-name' => array(
                new Segment('/:foo-bar'),
                '/baz',
                null,
                array('foo-bar' => 'baz')
            ),
            'url-encoded-parameters-are-decoded' => array(
                new Segment('/:foo'),
                '/foo+bar',
                null,
                array('foo' => 'foo bar')
            ),
            'urlencode-flaws-corrected' => array(
                new Segment('/:foo'),
                "/!$&'()*,-.:;=@_~",
                null,
                array('foo' => "!$&'()*,-.:;=@_~")
            ),
            'empty-matches-are-replaced-with-defaults' => array(
                new Segment('/foo[/:bar]/baz-:baz', array(), array('bar' => 'bar')),
                '/foo/baz-baz',
                null,
                array('bar' => 'bar', 'baz' => 'baz')
            ),
        );
    }

    public static function parseExceptionsProvider()
    {
        return array(
            'unbalanced-brackets' => array(
                '[',
                'Zend\Mvc\Router\Exception\RuntimeException',
                'Found unbalanced brackets'
            ),
            'closing-bracket-without-opening-bracket' => array(
                ']',
                'Zend\Mvc\Router\Exception\RuntimeException',
                'Found closing bracket without matching opening bracket'
            ),
            'empty-parameter-name' => array(
                ':',
                'Zend\Mvc\Router\Exception\RuntimeException',
                'Found empty parameter name'
            ),
            'translated-literal-without-closing-backet' => array(
                '{test',
                'Zend\Mvc\Router\Exception\RuntimeException',
                'Translated literal missing closing bracket'
            ),
            'translated-parameter-without-closing-backet' => array(
                ':{test',
                'Zend\Mvc\Router\Exception\RuntimeException',
                'Translated parameter missing closing bracket'
            ),
        );
    }

    /**
     * @dataProvider routeProvider
     * @param        Segment $route
     * @param        string  $path
     * @param        integer $offset
     * @param        array   $params
     */
    public function testMatching(Segment $route, $path, $offset, array $params = null)
    {
        $request = new Request();
        $request->setUri('http://example.com' . $path);
        $match = $route->match($request, $offset);

        if ($params === null) {
            $this->assertNull($match);
        } else {
            $this->assertInstanceOf('Zend\Mvc\Router\Http\RouteMatch', $match);

            if ($offset === null) {
                $this->assertEquals(strlen($path), $match->getLength());
            }

            foreach ($params as $key => $value) {
                $this->assertEquals($value, $match->getParam($key));
            }
        }
    }

    /**
     * @dataProvider routeProvider
     * @param        Segment $route
     * @param        string  $path
     * @param        integer $offset
     * @param        array   $params
     */
    public function testAssembling(Segment $route, $path, $offset, array $params = null)
    {
        if ($params === null) {
            // Data which will not match are not tested for assembling.
            return;
        }

        $result = $route->assemble($params);

        if ($offset !== null) {
            $this->assertEquals($offset, strpos($path, $result, $offset));
        } else {
            $this->assertEquals($path, $result);
        }
    }

    /**
     * @dataProvider parseExceptionsProvider
     * @param        string $route
     * @param        string $exceptionName
     * @param        string $exceptionMessage
     */
    public function testParseExceptions($route, $exceptionName, $exceptionMessage)
    {
        $this->setExpectedException($exceptionName, $exceptionMessage);
        new Segment($route);
    }

    public function testAssemblingWithMissingParameterInRoot()
    {
        $this->setExpectedException('Zend\Mvc\Router\Exception\InvalidArgumentException', 'Missing parameter "foo"');
        $route = new Segment('/:foo');
        $route->assemble();
    }

    public function testBuildTranslatedLiteral()
    {
        $this->setExpectedException('Zend\Mvc\Router\Exception\RuntimeException', 'Translated literals are not implemented yet');
        $route = new Segment('/{foo}');
    }

    public function testBuildTranslatedParameter()
    {
        $this->setExpectedException('Zend\Mvc\Router\Exception\RuntimeException', 'Translated parameters are not implemented yet');
        $route = new Segment('/:{foo}');
    }

    public function testNoMatchWithoutUriMethod()
    {
        $route   = new Segment('/foo');
        $request = new BaseRequest();

        $this->assertNull($route->match($request));
    }

    public function testAssemblingWithExistingChild()
    {
        $route = new Segment('/[:foo]', array(), array('foo' => 'bar'));
        $path = $route->assemble(array(), array('has_child' => true));

        $this->assertEquals('/bar', $path);
    }

    public function testFactory()
    {
        $tester = new FactoryTester($this);
        $tester->testFactory(
            'Zend\Mvc\Router\Http\Segment',
            array(
                'route' => 'Missing "route" in options array'
            ),
            array(
                'route'       => '/:foo[/:bar{-}]',
                'constraints' => array('foo' => 'bar')
            )
        );
    }
}
