import ProfileController from './ProfileController'
import PasswordController from './PasswordController'
import LanguageController from './LanguageController'
import NotificationController from './NotificationController'
import ApiTokenController from './ApiTokenController'

const Settings = {
    ProfileController: Object.assign(ProfileController, ProfileController),
    PasswordController: Object.assign(PasswordController, PasswordController),
    LanguageController: Object.assign(LanguageController, LanguageController),
    NotificationController: Object.assign(NotificationController, NotificationController),
    ApiTokenController: Object.assign(ApiTokenController, ApiTokenController),
}

export default Settings