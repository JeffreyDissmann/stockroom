import BoxController from './BoxController'
import RelatedItemController from './RelatedItemController'
import PaperlessLinkController from './PaperlessLinkController'

const Items = {
    BoxController: Object.assign(BoxController, BoxController),
    RelatedItemController: Object.assign(RelatedItemController, RelatedItemController),
    PaperlessLinkController: Object.assign(PaperlessLinkController, PaperlessLinkController),
}

export default Items