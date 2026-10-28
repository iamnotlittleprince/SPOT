import React from "react";
import ReactDOM from "react-dom/client";
import App from "./App";
import { SpotErrorBoundary, RequestErrorHost } from "./ErrorScreen";
import "../css/app.css";
import "../css/popups.css";

ReactDOM.createRoot(document.getElementById("root")).render(
  <React.StrictMode>
    <SpotErrorBoundary><App /><RequestErrorHost /></SpotErrorBoundary>
  </React.StrictMode>,
);

import "../css/accessibility.css";
import "../css/responsive.css";
