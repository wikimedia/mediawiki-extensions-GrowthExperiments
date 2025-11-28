const EXPERIMENT_NAME = 'growthexperiments-revise-tone';
const STREAM_NAME = 'mediawiki.product_metrics.contributors.experiments';

const useExperiment = () => {
	if ( !mw.xLab ) {
		mw.log.warn( 'Failed to log experiment interaction because mw.xLab is not defined' );
		return { send: () => {} };
	}
	const experiment = mw.xLab.getExperiment( EXPERIMENT_NAME );
	experiment.setStream( STREAM_NAME );
	return experiment;
};

module.exports = exports = useExperiment;
