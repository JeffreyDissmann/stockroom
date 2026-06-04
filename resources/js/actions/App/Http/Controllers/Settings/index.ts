import ProfileController from './ProfileController'
import PasswordController from './PasswordController'
import LanguageController from './LanguageController'
import ApiTokenController from './ApiTokenController'

const Settings = {
    ProfileController: Object.assign(ProfileController, ProfileController),
    PasswordController: Object.assign(PasswordController, PasswordController),
    LanguageController: Object.assign(LanguageController, LanguageController),
    ApiTokenController: Object.assign(ApiTokenController, ApiTokenController),
}

export default Settings