<?php

declare(strict_types=1);

namespace Ghostwriter\Serializer;

interface SerializerInterface
{
    /**
     * Deserializes a JSON string into an object.
     *
     * @param string $json the JSON string to deserialize
     *
     * @throws SerializerExceptionInterface if the JSON cannot be deserialized into an object
     *
     * @return object the deserialized object
     */
    public function deserialize(string $json): object;

    /**
     * Serializes an object into a JSON string.
     *
     * @param object $object the object to serialize
     *
     * @throws SerializerExceptionInterface if the object cannot be serialized
     *
     * @return string the JSON representation of the object
     */
    public function serialize(object $object): string;
}
