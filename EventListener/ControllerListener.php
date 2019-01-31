<?php

namespace Bilyiv\RequestDataBundle\EventListener;

use Bilyiv\RequestDataBundle\Event\DeserializedEvent;
use Bilyiv\RequestDataBundle\Events;
use Bilyiv\RequestDataBundle\Exception\DeserializationException;
use Bilyiv\RequestDataBundle\Extractor\ExtractorInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Vladyslav Bilyi <beliyvladislav@gmail.com>
 */
class ControllerListener
{
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var ExtractorInterface
     */
    private $extractor;

    /**
     * @var string
     */
    private $prefix;

    public function __construct(
        SerializerInterface $serializer,
        EventDispatcherInterface $dispatcher,
        ExtractorInterface $extractor,
        string $prefix
    ) {
        $this->serializer = $serializer;
        $this->dispatcher = $dispatcher;
        $this->extractor = $extractor;
        $this->prefix = $prefix;
    }

    /**
     * @param FilterControllerEvent $event
     * @throws DeserializationException
     * @throws \ReflectionException
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        $format = $this->extractor->extractFormat();
        if (!$format) {
            return;
        }

        $data = $this->extractor->extractData();
        if (!$data) {
            return;
        }

        $controller = $event->getController();
        if (!is_array($controller)) {
            return;
        }

        $controllerClass = (new \ReflectionClass($controller[0]));
        if ($controllerClass->isAbstract()) {
            return;
        }

        $parameters = $controllerClass->getMethod($controller[1])->getParameters();
        foreach ($parameters as $parameter) {
            $class = $parameter->getClass();
            if (!$class || strpos($class->getName(), $this->prefix) !== 0) {
                continue;
            }

            try {
                $requestData = $this->serializer->deserialize($data, $class->getName(), $format);
            } catch (\Exception $exception) {
                throw new DeserializationException($exception->getMessage(), $exception->getCode(), $exception);
            }

            $event->getRequest()->attributes->set($parameter->getName(), $requestData);

            $this->dispatcher->dispatch(Events::DESERIALIZED, new DeserializedEvent($requestData));
        }
    }
}
