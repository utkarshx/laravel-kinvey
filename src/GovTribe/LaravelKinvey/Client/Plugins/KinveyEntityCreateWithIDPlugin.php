<?php namespace GovTribe\LaravelKinvey\Client\Plugins;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Guzzle\Common\Event;

class KinveyEntityCreateWithIDPlugin extends KinveyGuzzlePlugin implements EventSubscriberInterface
{

	/**
	 * Return the array of subscribed events.
	 *
	 * @return array
	 */
	public static function getSubscribedEvents()
	{
		return array('command.before_prepare' => 'beforePrepare');
	}

	/**
	 * If an entity create request is made with an ID parameter,
	 * change the HTTP method from POST to PUT. This allows
	 * adding entities with custom IDs.
	 *
	 * @param  Guzzle\Common\Event
	 * @return void
	 */
	public function beforePrepare(Event $event)
	{
		$command = $event['command'];

		if ($command->getName() !== 'createEntity') return;
		if (!$command['_id']) return;

		$operation = $command->getOperation();
		$operation->setHttpMethod('PUT');
	}
}
