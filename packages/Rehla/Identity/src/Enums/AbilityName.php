<?php

declare(strict_types=1);

namespace Rehla\Identity\Enums;

enum AbilityName: string
{
    case AdminOverviewView = 'admin.overview.view';
    case ServicesView = 'services.view';
    case ServicesManage = 'services.manage';
    case FormsView = 'forms.view';
    case FormsDraft = 'forms.draft';
    case FormsPublish = 'forms.publish';
    case CustomersView = 'customers.view';
    case CustomersViewSensitive = 'customers.view_sensitive';
    case CustomersManageStatus = 'customers.manage_status';
    case TravelersView = 'travelers.view';
    case TravelersViewSensitive = 'travelers.view_sensitive';
    case WalletsView = 'wallets.view';
    case BanksView = 'banks.view';
    case BanksManage = 'banks.manage';
    case TopUpsView = 'topups.view';
    case TopUpsReview = 'topups.review';
    case TopUpsSettingsManage = 'topups.settings.manage';
    case OrdersView = 'orders.view';
    case ExecutionsView = 'executions.view';
    case ExecutionsViewSensitive = 'executions.view_sensitive';
    case ExecutionsTransition = 'executions.transition';
    case ExecutionsNote = 'executions.note';
    case DocumentsViewSensitive = 'documents.view_sensitive';
    case ContentView = 'content.view';
    case ContentManage = 'content.manage';
    case NotificationsView = 'notifications.view';
    case NotificationsReplay = 'notifications.replay';
    case AccessView = 'access.view';
    case AccessManage = 'access.manage';
    case AuditView = 'audit.view';

    public function requiresRecentMfa(): bool
    {
        return match ($this) {
            self::TopUpsReview,
            self::TopUpsSettingsManage,
            self::AccessManage,
            self::AuditView => true,
            default => false,
        };
    }
}
