import { render } from 'preact'
import { App } from './app.tsx'
import { appConfig } from './config/config.ts'

render(<App />, appConfig.root)
