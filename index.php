<?php

require 'vendor/autoload.php';

use Github\Client;

const KEY = 'GITHUB_ACCESS_TOKEN';

/**
 * CLA Validation Server
 *
 * @package ClaServer
 */
class Server
{
	/**
	 * @var Client
	 */
	private $client;

	/**
	 * Server constructor.
	 */
	private function __construct ()
	{
		$this->client = new Client();
		$this->client->authenticate(KEY, Client::AUTH_HTTP_TOKEN);
	}

	/**
	 * Entrypoint to the server
	 * @return void
	 */
	public static function handle ()
	{
		return (new static())
			->handlePayload();
	}

	/**
	 * Handle the information supplied by Github's webhook call
	 */
	public function handlePayload ()
	{
		if(!$_SERVER['HTTP_X_GITHUB_EVENT']) return;
		if($_SERVER['HTTP_X_GITHUB_EVENT'] !== "pull_request") return;

		$json = file_get_contents('php://input');
		$payload = json_decode($json);

		switch ($payload->action) {
			case "opened":
			case "edited":
				return $this->checkClaForUsers($payload);
		}
	}

	/**
	 * Check if user did supply the CLA needed for pull-requests
	 *
	 * @param object $payload
	 */
	private function checkClaForUsers ($payload)
	{
		$user = $payload->repository->owner->login;
		$repo = $payload->repository->name;
		$sha = $payload->pull_request->head->sha;

		$this->createPendingStatus($user, $repo, $sha);

		if($user == 'LostKobrakai')
			$this->createErrorStatus($user, $repo, $sha);
		else
			$this->createSuccessStatus($user, $repo, $sha);
	}

	/**
	 * @param $user
	 * @param $repo
	 * @param $sha
	 * @return mixed
	 */
	public function createPendingStatus ($user, $repo, $sha)
	{
		$params = [
			'state' => 'pending',
			'description' => 'Evaluating Contributor License Agreement',
		];
		return $this->createStatus($user, $repo, $sha, $params);
	}

	/**
	 * @param $user
	 * @param $repo
	 * @param $sha
	 * @return mixed
	 */
	public function createSuccessStatus ($user, $repo, $sha)
	{
		$params = [
			'state' => 'success',
			'description' => 'Contributor License Agreement supplied.',
		];
		return $this->createStatus($user, $repo, $sha, $params);
	}

	/**
	 * @param $user
	 * @param $repo
	 * @param $sha
	 * @return mixed
	 */
	public function createErrorStatus ($user, $repo, $sha)
	{
		$params = [
			'state' => 'failure',
			'description' => 'No Contributor License Agreement supplied.',
		];
		return $this->createStatus($user, $repo, $sha, $params);
	}

	/**
	 * @param $user
	 * @param $repo
	 * @param $sha
	 * @param $params
	 * @return mixed
	 */
	protected function createStatus ($user, $repo, $sha, $params)
	{
		$params = array_merge([
			'state' => 'pending',
			'target_url' => 'https://processwire.com/about/license/cla/',
			'description' => '',
			'context' => 'processwire/CLA',
		], $params);

		return $this->client->api('repo')->statuses()
			->create($user, $repo, $sha, $params);
	}
}

Server::handle();