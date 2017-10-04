<?php
/**
 * @copyright 2017 Hostnet B.V.
 */
declare(strict_types=1);
namespace Hostnet\Component\Resolver\Bundler;

use Hostnet\Component\Resolver\Bundler\Pipeline\ContentPipelineInterface;
use Hostnet\Component\Resolver\ConfigInterface;
use Hostnet\Component\Resolver\File;
use Hostnet\Component\Resolver\FileSystem\ReaderInterface;
use Hostnet\Component\Resolver\FileSystem\WriterInterface;
use Hostnet\Component\Resolver\Import\Dependency;
use Hostnet\Component\Resolver\Import\ImportFinderInterface;
use Hostnet\Component\Resolver\Import\RootFile;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Log\NullLogger;

/**
 * @covers \Hostnet\Component\Resolver\Bundler\PipelineBundler
 */
class PipelineBundlerTest extends TestCase
{
    private $finder;
    private $pipeline;
    private $config;

    /**
     * @var PipelineBundler
     */
    private $pipeline_bundler;

    protected function setUp()
    {
        $this->finder   = $this->prophesize(ImportFinderInterface::class);
        $this->pipeline = $this->prophesize(ContentPipelineInterface::class);
        $this->config   = $this->prophesize(ConfigInterface::class);

        $this->pipeline_bundler = new PipelineBundler(
            $this->finder->reveal(),
            $this->pipeline->reveal(),
            new NullLogger(),
            $this->config->reveal()
        );
    }

    public function testExecute()
    {
        $reader = $this->prophesize(ReaderInterface::class);
        $writer = $this->prophesize(WriterInterface::class);

        $this->config->getWebRoot()->willReturn('');
        $this->config->getOutputFolder()->willReturn('dev');
        $this->config->getSourceRoot()->willReturn('');
        $this->config->isDev()->willReturn(true);
        $this->config->getCacheDir()->willReturn(__DIR__ . '/dev');
        $this->config->cwd()->willReturn(__DIR__);
        $this->config->getEntryPoints()->willReturn(['foo.js']);
        $this->config->getAssetFiles()->willReturn(['bar.js']);

        $entry_point1 = new RootFile(new File('foo.js'));
        $entry_point2 = new RootFile(new File('bar.js'));
        $entry_point3 = new RootFile(new File('asset.js'));

        $entry_point1->addChild(new Dependency(new File('asset.js'), false, true));
        $entry_point1->addChild(new Dependency(new File('asset.js'), false, true));

        $this->finder->all(Argument::that(function (File $file) {
            return $file->path === 'foo.js';
        }))->willReturn($entry_point1);
        $this->finder->all(Argument::that(function (File $file) {
            return $file->path === 'bar.js';
        }))->willReturn($entry_point2);
        $this->finder->all(Argument::that(function (File $file) {
            return $file->path === 'asset.js';
        }))->willReturn($entry_point3);

        $this->pipeline
            ->push([$entry_point1], new File('dev/foo.bundle.js'), $reader->reveal())
            ->willReturn('foo.js bundle');
        $this->pipeline
            ->push([], new File('dev/foo.vendor.js'), $reader->reveal())
            ->willReturn('foo.js vendor');
        $this->pipeline
            ->push([$entry_point2], new File('dev/bar.js'), $reader->reveal())
            ->willReturn('bar.js content');
        $this->pipeline
            ->push([$entry_point3], new File('dev/asset.js'), $reader->reveal())
            ->willReturn('asset.js content');
        $this->pipeline->peek(new File('bar.js'))->willReturn('js');
        $this->pipeline->peek(new File('asset.js'))->willReturn('js');

        $reader->read(Argument::that(function (File $file) {
            return false !== strpos($file->path, '/src/Resources/require.js');
        }))->willReturn('foobar');

        $writer->write(Argument::that(function (File $file) {
            return $file->path === 'dev/require.js';
        }), 'foobar')->shouldBeCalled();
        $writer->write(Argument::that(function (File $file) {
            return $file->path === 'dev/foo.bundle.js';
        }), 'foo.js bundle')->shouldBeCalled();
        $writer->write(Argument::that(function (File $file) {
            return $file->path === 'dev/foo.vendor.js';
        }), 'foo.js vendor')->shouldBeCalled();
        $writer->write(Argument::that(function (File $file) {
            return $file->path === 'dev/bar.js';
        }), 'bar.js content')->shouldBeCalled();
        $writer->write(Argument::that(function (File $file) {
            return $file->path === 'dev/asset.js';
        }), 'asset.js content')->shouldBeCalled();

        $this->pipeline_bundler->execute($reader->reveal(), $writer->reveal());
    }

    public function testExecuteNotChanged()
    {
        $reader = $this->prophesize(ReaderInterface::class);
        $writer = $this->prophesize(WriterInterface::class);

        $this->config->getWebRoot()->willReturn('');
        $this->config->getOutputFolder()->willReturn('dev');
        $this->config->getSourceRoot()->willReturn('');
        $this->config->isDev()->willReturn(true);
        $this->config->getCacheDir()->willReturn(__DIR__ . '/dev');
        $this->config->cwd()->willReturn(__DIR__);
        $this->config->getEntryPoints()->willReturn(['foobar.js']);
        $this->config->getAssetFiles()->willReturn([]);

        $entry_point1 = new RootFile(new File('foobar.js'));

        $this->finder->all(Argument::that(function (File $file) {
            return $file->path === 'foobar.js';
        }))->willReturn($entry_point1);

        $this->pipeline
            ->push([$entry_point1], new File('dev/foobar.bundle.js'), $reader->reveal())
            ->willReturn('foobar.js bundle');
        $this->pipeline
            ->push([], new File('dev/foobar.vendor.js'), $reader->reveal())
            ->willReturn('foobar.js vendor');

        $reader->read(Argument::that(function (File $file) {
            return false !== strpos($file->path, '/src/Resources/require.js');
        }))->willReturn('foobar');

        $writer->write(Argument::that(function (File $file) {
            return $file->path === 'dev/require.js';
        }), 'foobar')->shouldBeCalled();

        $this->pipeline_bundler->execute($reader->reveal(), $writer->reveal());
    }
}