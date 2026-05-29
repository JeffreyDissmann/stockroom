import BoxController from './BoxController'
import RelatedItemController from './RelatedItemController'

const Items = {
    BoxController: Object.assign(BoxController, BoxController),
    RelatedItemController: Object.assign(RelatedItemController, RelatedItemController),
}

export default Items