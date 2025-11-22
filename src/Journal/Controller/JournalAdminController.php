<?php

/**
 * This file is part of the bitrix24-php-lib package.
 *
 * © Maksim Mesilov <mesilov.maxim@gmail.com>
 *
 * For the full copyright and license information, please view the MIT-LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Bitrix24\Lib\Journal\Controller;

use Bitrix24\Lib\Journal\Entity\LogLevel;
use Bitrix24\Lib\Journal\ReadModel\JournalItemReadRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Admin controller for journal management
 */
#[Route('/admin/journal', name: 'journal_admin_')]
class JournalAdminController extends AbstractController
{
    public function __construct(
        private readonly JournalItemReadRepository $journalReadRepository
    ) {
    }

    /**
     * List journal items with filters and pagination
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $domainUrl = $request->query->get('domain');
        $levelValue = $request->query->get('level');
        $label = $request->query->get('label');

        $level = null;
        if ($levelValue && in_array($levelValue, array_column(LogLevel::cases(), 'value'), true)) {
            $level = LogLevel::from($levelValue);
        }

        $pagination = $this->journalReadRepository->findWithFilters(
            domainUrl: $domainUrl ?: null,
            level: $level,
            label: $label ?: null,
            page: $page,
            limit: 50
        );

        $availableDomains = $this->journalReadRepository->getAvailableDomains();
        $availableLabels = $this->journalReadRepository->getAvailableLabels();

        return $this->render('@Journal/admin/list.html.twig', [
            'pagination' => $pagination,
            'currentFilters' => [
                'domain' => $domainUrl,
                'level' => $levelValue,
                'label' => $label,
            ],
            'availableDomains' => $availableDomains,
            'availableLabels' => $availableLabels,
            'logLevels' => LogLevel::cases(),
        ]);
    }

    /**
     * Show journal item details
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id): Response
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (\InvalidArgumentException) {
            throw $this->createNotFoundException('Invalid journal item ID');
        }

        $journalItem = $this->journalReadRepository->findById($uuid);

        if (!$journalItem) {
            throw $this->createNotFoundException('Journal item not found');
        }

        return $this->render('@Journal/admin/show.html.twig', [
            'item' => $journalItem,
        ]);
    }
}
