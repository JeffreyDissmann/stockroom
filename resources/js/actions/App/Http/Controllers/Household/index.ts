import CustomFieldController from './CustomFieldController'
import BackupController from './BackupController'
import SearchIndexController from './SearchIndexController'
import InvitationController from './InvitationController'
import PreferencesController from './PreferencesController'
import ResetController from './ResetController'
import ImportController from './ImportController'
import MemberController from './MemberController'

const Household = {
    CustomFieldController: Object.assign(CustomFieldController, CustomFieldController),
    BackupController: Object.assign(BackupController, BackupController),
    SearchIndexController: Object.assign(SearchIndexController, SearchIndexController),
    InvitationController: Object.assign(InvitationController, InvitationController),
    PreferencesController: Object.assign(PreferencesController, PreferencesController),
    ResetController: Object.assign(ResetController, ResetController),
    ImportController: Object.assign(ImportController, ImportController),
    MemberController: Object.assign(MemberController, MemberController),
}

export default Household