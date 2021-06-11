/**
 * Internal dependencies
 */
import ELEMENTS from './block-wrapper-elements';
var ExtendedBlockComponent = ELEMENTS.reduce(function (acc, element) {
  acc[element] = element;
  return acc;
}, String);
export var Block = ExtendedBlockComponent;
//# sourceMappingURL=block-wrapper.native.js.map