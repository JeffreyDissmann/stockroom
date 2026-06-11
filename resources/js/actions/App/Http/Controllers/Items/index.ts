import BulkController from './BulkController'
import BoxController from './BoxController'
import RelatedItemController from './RelatedItemController'
import PaperlessLinkController from './PaperlessLinkController'
import PaperlessFieldSuggestionController from './PaperlessFieldSuggestionController'
import HomeAssistantLinkController from './HomeAssistantLinkController'
import BatteryController from './BatteryController'
import MaintenanceTaskController from './MaintenanceTaskController'
import MaintenanceEntryController from './MaintenanceEntryController'

const Items = {
    BulkController: Object.assign(BulkController, BulkController),
    BoxController: Object.assign(BoxController, BoxController),
    RelatedItemController: Object.assign(RelatedItemController, RelatedItemController),
    PaperlessLinkController: Object.assign(PaperlessLinkController, PaperlessLinkController),
    PaperlessFieldSuggestionController: Object.assign(PaperlessFieldSuggestionController, PaperlessFieldSuggestionController),
    HomeAssistantLinkController: Object.assign(HomeAssistantLinkController, HomeAssistantLinkController),
    BatteryController: Object.assign(BatteryController, BatteryController),
    MaintenanceTaskController: Object.assign(MaintenanceTaskController, MaintenanceTaskController),
    MaintenanceEntryController: Object.assign(MaintenanceEntryController, MaintenanceEntryController),
}

export default Items