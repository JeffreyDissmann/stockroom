import BulkController from './BulkController'
import BoxController from './BoxController'
import RelatedItemController from './RelatedItemController'
import PaperlessLinkController from './PaperlessLinkController'

const Items = {
    BulkController: Object.assign(BulkController, BulkController),
    BoxController: Object.assign(BoxController, BoxController),
    RelatedItemController: Object.assign(RelatedItemController, RelatedItemController),
    PaperlessLinkController: Object.assign(PaperlessLinkController, PaperlessLinkController),
}

export default Items