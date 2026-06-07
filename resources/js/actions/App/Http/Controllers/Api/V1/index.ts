import UserController from './UserController'
import StatisticsController from './StatisticsController'
import ItemController from './ItemController'
import RoomController from './RoomController'
import TagController from './TagController'
import SearchController from './SearchController'
import HomeAssistantLinkController from './HomeAssistantLinkController'
import MaintenanceTaskController from './MaintenanceTaskController'

const V1 = {
    UserController: Object.assign(UserController, UserController),
    StatisticsController: Object.assign(StatisticsController, StatisticsController),
    ItemController: Object.assign(ItemController, ItemController),
    RoomController: Object.assign(RoomController, RoomController),
    TagController: Object.assign(TagController, TagController),
    SearchController: Object.assign(SearchController, SearchController),
    HomeAssistantLinkController: Object.assign(HomeAssistantLinkController, HomeAssistantLinkController),
    MaintenanceTaskController: Object.assign(MaintenanceTaskController, MaintenanceTaskController),
}

export default V1