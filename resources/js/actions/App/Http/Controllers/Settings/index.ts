import ProfileController from './ProfileController'
import PasswordController from './PasswordController'
import LanguageController from './LanguageController'

const Settings = {
    ProfileController: Object.assign(ProfileController, ProfileController),
    PasswordController: Object.assign(PasswordController, PasswordController),
    LanguageController: Object.assign(LanguageController, LanguageController),
}

export default Settings