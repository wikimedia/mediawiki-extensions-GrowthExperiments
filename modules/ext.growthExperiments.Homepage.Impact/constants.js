// The number of columns to show in the streak graphic. Columns
// will be represented as days.
const DEFAULT_STREAK_TIME_FRAME = 60;
// The string to use for data displays on empty states
const NO_DATA_CHARACTER = '...';
// References ComputedUserImpactLookup::MAX_EDITS / MAX_THANKS. If we get exactly this number
// for edit count or thanks count, there are probably more.
const DATA_ROWS_LIMIT = 1000;

exports = module.exports = {
	DEFAULT_STREAK_TIME_FRAME,
	NO_DATA_CHARACTER,
	DATA_ROWS_LIMIT
};
