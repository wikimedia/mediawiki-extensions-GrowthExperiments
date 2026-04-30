const EXPERIMENT_NAME = 'growthexperiments-revise-tone';

const useExperiment = () => {
	if ( !mw.testKitchen ) {
		mw.log.warn( 'Failed to log experiment interaction because mw.testKitchen is not defined' );
		return { send: () => {} };
	}
	return mw.testKitchen.compat.getExperiment( EXPERIMENT_NAME );
};

module.exports = exports = useExperiment;
