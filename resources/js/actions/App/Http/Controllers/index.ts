import DashboardController from './DashboardController'
import SearchController from './SearchController'
import ActivityController from './ActivityController'
import ItemController from './ItemController'
import ItemPhotoAnalysisController from './ItemPhotoAnalysisController'
import AssistantController from './AssistantController'
import ImageSearchController from './ImageSearchController'
import ItemImageController from './ItemImageController'
import TagController from './TagController'
import Settings from './Settings'
import Household from './Household'
import Auth from './Auth'

const Controllers = {
    DashboardController: Object.assign(DashboardController, DashboardController),
    SearchController: Object.assign(SearchController, SearchController),
    ActivityController: Object.assign(ActivityController, ActivityController),
    ItemController: Object.assign(ItemController, ItemController),
    ItemPhotoAnalysisController: Object.assign(ItemPhotoAnalysisController, ItemPhotoAnalysisController),
    AssistantController: Object.assign(AssistantController, AssistantController),
    ImageSearchController: Object.assign(ImageSearchController, ImageSearchController),
    ItemImageController: Object.assign(ItemImageController, ItemImageController),
    TagController: Object.assign(TagController, TagController),
    Settings: Object.assign(Settings, Settings),
    Household: Object.assign(Household, Household),
    Auth: Object.assign(Auth, Auth),
}

export default Controllers