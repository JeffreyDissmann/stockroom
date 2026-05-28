import CustomFieldController from './CustomFieldController'
import BackupController from './BackupController'
import ImportController from './ImportController'
import SearchIndexController from './SearchIndexController'
import InvitationController from './InvitationController'
import ResetController from './ResetController'
import MemberController from './MemberController'

const Household = {
    CustomFieldController: Object.assign(CustomFieldController, CustomFieldController),
    BackupController: Object.assign(BackupController, BackupController),
    ImportController: Object.assign(ImportController, ImportController),
    SearchIndexController: Object.assign(SearchIndexController, SearchIndexController),
    InvitationController: Object.assign(InvitationController, InvitationController),
    ResetController: Object.assign(ResetController, ResetController),
    MemberController: Object.assign(MemberController, MemberController),
}

export default Household