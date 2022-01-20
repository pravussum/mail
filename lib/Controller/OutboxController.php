<?php

declare(strict_types=1);

/**
 * Mail App
 *
 * @copyright 2022 Anna Larch <anna.larch@gmx.net>
 *
 * @author Anna Larch <anna.larch@gmx.net>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Mail\Controller;

use OCA\Mail\Contracts\ILocalMailbox;
use OCA\Mail\Db\LocalMailboxMessage;
use OCA\Mail\Exception\ClientException;
use OCA\Mail\Exception\ServiceException;
use OCA\Mail\Service\AccountService;
use OCA\Mail\Service\OutboxService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUser;

class OutboxController extends Controller {

	/** @var OutboxService */
	private $service;

	/** @var IUser */
	private $user;

	/** @var AccountService */
	private $accountService;

	public function __construct(string $appName,
								IUser $user,
								IRequest $request,
								ILocalMailbox $service,
	AccountService $accountService) {
		parent::__construct($appName, $request);
		$this->user = $user;
		$this->service = $service;
		$this->accountService = $accountService;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return JSONResponse
	 */
	public function index(): JSONResponse {
		try {
			return new JSONResponse(
				$this->service->getMessages($this->user->getUID())
			);
		} catch (ServiceException $e) {
			return new JSONResponse($e->getMessage(), $e->getCode());
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 * @return JSONResponse
	 */
	public function get(int $id): JSONResponse {
		try {
			$message = $this->service->getMessage($id);
		} catch (ServiceException $e) {
			return new JSONResponse('Could not find message' . $e->getMessage(), $e->getCode());
		}
		try {
			$this->accountService->find($this->user->getUID(), $message->getAccountId());
		} catch (ClientException $e) {
			return new JSONResponse('Could not find account for user ' . $e->getMessage(), $e->getHttpCode());
		}
		return new JSONResponse(
			$message
		);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $accountId
	 * @param int $sendAt
	 * @param string $subject
	 * @param string $text
	 * @param bool $isHtml
	 * @param bool $isMdn
	 * @param string $inReplyToMessageId
	 * @param array $recipients
	 * @param array $attachmentIds
	 * @return JSONResponse
	 */
	public function save(
		int    $accountId,
		int    $sendAt,
		string $subject,
		string $text,
		bool   $isHtml,
		bool   $isMdn,
		string $inReplyToMessageId,
		array  $recipients,
		array  $attachmentIds
	): JSONResponse {
		try {
			$this->accountService->find($this->user->getUID(), $accountId);
		} catch (ClientException $e) {
			return new JSONResponse('Could not find account for user ' . $e->getMessage(), $e->getCode());
		}

		$message = new LocalMailboxMessage();
		$message->setAccountId($accountId);
		$message->setSendAt($sendAt);
		$message->setSubject($subject);
		$message->setBody($text);
		$message->setHtml($isHtml);
		$message->setMdn($isMdn);
		$message->setInReplyToMessageId($inReplyToMessageId);

		try {
			$this->service->saveMessage($message, $recipients, $attachmentIds);
		} catch (ServiceException $e) {
			return new JSONResponse('Could not save outbox message' . $e->getMessage(), $e->getCode());
		}
		return new JSONResponse(
			$message
		);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 * @return JSONResponse
	 * @throws ClientException
	 * @throws ServiceException
	 */
	public function send(int $id):JSONResponse {
		try {
			$message = $this->service->getMessage($id);
		} catch (ServiceException $e) {
			return new JSONResponse('Could not find message' . $e->getMessage(), $e->getCode());
		}
		try {
			$account = $this->accountService->find($this->user->getUID(), $message->getAccountId());
		} catch (ClientException $e) {
			return new JSONResponse('Could not find account for user ' . $e->getMessage(), $e->getCode());
		}
		return new JSONResponse(
			$this->service->sendMessage($message, $account)
		);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 * @return JSONResponse
	 * @throws ClientException
	 * @throws ServiceException
	 */
	public function delete(int $id): JSONResponse {
		try {
			$message = $this->service->getMessage($id);
		} catch (ServiceException $e) {
			return new JSONResponse('Could not find message' . $e->getMessage(), $e->getCode());
		}
		try {
			$this->accountService->find($this->user->getUID(), $message->getAccountId());
		} catch (ClientException $e) {
			return new JSONResponse('Could not find account for user ' . $e->getMessage(), $e->getCode());
		}
		$this->service->deleteMessage($message);
		return new JSONResponse('', 200);
	}
}
